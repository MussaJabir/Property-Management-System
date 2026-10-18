<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages;

use App\Models\FeedbackReport;
use App\Models\SuperAdminUser;
use App\Models\User;
use App\Notifications\FeedbackReceivedNotification;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

use function Illuminate\Support\defer;

/**
 * "Send feedback": any staff member can tell BJP about a problem, an idea or
 * a question, with an optional screenshot. BJP reads it in the admin panel
 * and gets an email. Reached from the user menu, not the sidebar.
 */
class SendFeedback extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'feedback';

    protected string $view = 'filament.operator.pages.send-feedback';

    /** Reports one person may send per hour. */
    public const PER_HOUR = 10;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getTitle(): string
    {
        return __('Send feedback to BJP');
    }

    public function getSubheading(): string
    {
        return __('Tell us what went wrong, what would help, or ask a question. We read every message.');
    }

    public function mount(): void
    {
        $this->form->fill([
            'type' => FeedbackReport::TYPE_PROBLEM,
            // The page they came from, so we can find the problem.
            'page_url' => $this->previousPage(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                ToggleButtons::make('type')
                    ->label(__('What is it about?'))
                    ->options(FeedbackReport::typeLabels())
                    ->icons([
                        FeedbackReport::TYPE_PROBLEM => Heroicon::OutlinedExclamationTriangle,
                        FeedbackReport::TYPE_IDEA => Heroicon::OutlinedLightBulb,
                        FeedbackReport::TYPE_QUESTION => Heroicon::OutlinedQuestionMarkCircle,
                    ])
                    ->inline()
                    ->required(),

                Textarea::make('message')
                    ->label(__('Your message'))
                    ->placeholder(__('What happened, and what did you expect? Which page or screen?'))
                    ->required()
                    ->minLength(5)
                    ->maxLength(5000)
                    ->rows(6),

                FileUpload::make('screenshot_path')
                    ->label(__('Screenshot (optional)'))
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->disk(config('filesystems.default'))
                    ->directory(fn (): string => 'feedback/'.auth()->user()?->tenant_id)
                    ->visibility('private'),

                Hidden::make('page_url'),
            ]);
    }

    public function send(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $key = 'feedback:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            Notification::make()->warning()->title(__('That\'s a lot of messages'))
                ->body(__('Please wait a little before sending more, or call us on +255 764 764 011.'))
                ->send();

            return;
        }

        $data = $this->form->getState();

        $report = FeedbackReport::create([
            'user_id' => $user->getKey(),
            'type' => $data['type'],
            'message' => $data['message'],
            'page_url' => $data['page_url'] ?? null,
            'screenshot_path' => $data['screenshot_path'] ?? null,
            'status' => FeedbackReport::STATUS_NEW,
        ]);
        RateLimiter::hit($key, 3600);

        // Email after the response: sending mail shouldn't hold the page up.
        defer(function () use ($report): void {
            try {
                NotificationFacade::send(SuperAdminUser::all(), new FeedbackReceivedNotification($report));
            } catch (Throwable $e) {
                Log::warning('Feedback email failed', ['report_id' => $report->getKey(), 'error' => $e->getMessage()]);
            }
        });

        $this->form->fill(['type' => FeedbackReport::TYPE_PROBLEM, 'page_url' => $data['page_url'] ?? null]);

        Notification::make()->success()
            ->title(__('Thank you, we got it'))
            ->body(__('The BJP team will look at it and get back to you.'))
            ->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var Collection<int, FeedbackReport> $mine */
        $mine = FeedbackReport::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get();

        return ['mine' => $mine];
    }

    private function previousPage(): ?string
    {
        $previous = url()->previous();

        return $previous !== url()->current() && str_starts_with($previous, url('/')) ? $previous : null;
    }
}
