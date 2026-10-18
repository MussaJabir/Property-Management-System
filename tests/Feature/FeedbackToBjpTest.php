<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\FeedbackReports\FeedbackReportResource;
use App\Filament\Admin\Resources\FeedbackReports\Pages\EditFeedbackReport;
use App\Filament\Admin\Resources\FeedbackReports\Pages\ListFeedbackReports;
use App\Filament\Operator\Pages\SendFeedback;
use App\Models\Client;
use App\Models\FeedbackReport;
use App\Models\SuperAdminUser;
use App\Models\User;
use App\Notifications\FeedbackReceivedNotification;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'fbco', 'name' => 'Feedback Co', 'status' => 'active']);
    $this->other = Client::create(['slug' => 'otherfb', 'name' => 'Other FB', 'status' => 'active']);
    $this->admin = SuperAdminUser::create(['name' => 'BJP', 'email' => 'admin@bjp.test', 'password' => 'secret-password']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->staff = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Receptionist Rehema',
        'email' => 'rehema@fb.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->staff->assignRole('receptionist');
    $this->staff->forceFill(['onboarding_completed_at' => now()])->save();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function asStaff(User $user, Client $client): void
{
    test()->actingAs($user, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($client);
}

it('lets any staff member send feedback with a screenshot, and emails BJP', function () {
    Notification::fake();
    $this->withoutDefer();
    Storage::fake(config('filesystems.default'));
    asStaff($this->staff, $this->client);

    Livewire::test(SendFeedback::class)
        ->fillForm([
            'type' => FeedbackReport::TYPE_PROBLEM,
            'message' => 'Check out button does nothing on room R2.',
            'screenshot_path' => UploadedFile::fake()->image('board.png'),
        ])
        ->call('send')
        ->assertHasNoFormErrors()
        ->assertNotified('Thank you, we got it')
        ->assertSee('What you have sent')
        ->assertSee('Check out button does nothing');

    $report = FeedbackReport::query()->sole();
    expect($report->tenant_id)->toBe($this->client->id)
        ->and($report->user_id)->toBe($this->staff->id)
        ->and($report->status)->toBe(FeedbackReport::STATUS_NEW)
        ->and($report->screenshot_path)->toStartWith('feedback/fbco/');
    Storage::disk(config('filesystems.default'))->assertExists((string) $report->screenshot_path);

    Notification::assertSentTo($this->admin, FeedbackReceivedNotification::class);
});

it('needs a message and limits how many one person sends an hour', function () {
    Notification::fake();
    asStaff($this->staff, $this->client);

    Livewire::test(SendFeedback::class)
        ->fillForm(['type' => FeedbackReport::TYPE_IDEA, 'message' => ''])
        ->call('send')
        ->assertHasFormErrors(['message' => 'required']);

    for ($i = 0; $i < SendFeedback::PER_HOUR; $i++) {
        Livewire::test(SendFeedback::class)->fillForm(['type' => FeedbackReport::TYPE_IDEA, 'message' => "Idea number {$i}"])->call('send');
    }

    Livewire::test(SendFeedback::class)
        ->fillForm(['type' => FeedbackReport::TYPE_IDEA, 'message' => 'One too many'])
        ->call('send')
        ->assertNotified('That\'s a lot of messages');

    expect(FeedbackReport::query()->count())->toBe(SendFeedback::PER_HOUR);
});

it('shows staff only their own workspace\'s reports, and BJP sees every client\'s', function () {
    tenancy()->initialize($this->other);
    FeedbackReport::create(['type' => 'question', 'message' => 'Other client question', 'status' => 'new']);
    Tenancy::end();
    tenancy()->initialize($this->client);
    FeedbackReport::create(['user_id' => $this->staff->id, 'type' => 'problem', 'message' => 'Our own problem', 'status' => 'new']);
    Tenancy::end();

    asStaff($this->staff, $this->client);
    Livewire::test(SendFeedback::class)->assertSee('Our own problem')->assertDontSee('Other client question');
    Tenancy::end();

    $this->actingAs($this->admin, 'super_admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListFeedbackReports::class)
        ->assertCanSeeTableRecords(FeedbackReport::query()->get());
    expect(FeedbackReportResource::getNavigationBadge())->toBe('2');
});

it('lets BJP reply and resolve; the sender sees the reply', function () {
    tenancy()->initialize($this->client);
    $report = FeedbackReport::create(['user_id' => $this->staff->id, 'type' => 'problem', 'message' => 'Receipt not printing', 'status' => 'new']);
    Tenancy::end();

    $this->actingAs($this->admin, 'super_admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(EditFeedbackReport::class, ['record' => $report->getKey()])
        ->assertSee('Receipt not printing')
        ->assertSchemaStateSet(['status' => FeedbackReport::STATUS_IN_PROGRESS])
        ->fillForm(['status' => FeedbackReport::STATUS_RESOLVED, 'reply' => 'Fixed — please refresh the page.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($report->fresh()?->status)->toBe(FeedbackReport::STATUS_RESOLVED)
        ->and($report->fresh()?->resolved_at)->not->toBeNull();

    asStaff($this->staff, $this->client);
    Livewire::test(SendFeedback::class)->assertSee('Fixed — please refresh the page.')->assertSee('Resolved');
});

it('serves screenshots, saved inside the workspace, only to signed-in BJP admins', function () {
    // Saved the way the operator page saves it: inside the client's tenancy,
    // where the local disk has a per-client root.
    tenancy()->initialize($this->client);
    Storage::disk(config('filesystems.default'))->put('feedback/fbco/shot.png', 'png-bytes');
    $report = FeedbackReport::create(['type' => 'problem', 'message' => 'See screenshot', 'status' => 'new', 'screenshot_path' => 'feedback/fbco/shot.png']);
    Tenancy::end();

    $this->get('/admin/feedback/'.$report->getKey().'/screenshot')->assertRedirect();

    $this->actingAs($this->admin, 'super_admin')
        ->get('/admin/feedback/'.$report->getKey().'/screenshot')
        ->assertOk()
        ->assertContent('png-bytes');

    $this->client->run(fn () => Storage::disk(config('filesystems.default'))->delete('feedback/fbco/shot.png'));
});
