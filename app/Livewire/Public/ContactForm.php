<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\ContactSubmission;
use App\Models\User;
use App\Notifications\ContactSubmissionReceivedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    public bool $sent = false;

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        if ($this->email === '' && $this->phone === '') {
            $this->addError('email', __('Please provide either an email or a phone number so we can reach you.'));

            return;
        }

        $client = tenant();

        $submission = ContactSubmission::create([
            'tenant_id' => $client->getKey(),
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'message' => $this->message,
            'status' => ContactSubmission::STATUS_NEW,
            'ip' => request()->ip(),
        ]);

        // Notify every active operator on this client. Failure to email
        // shouldn't lose the submission — it's already in the DB.
        try {
            User::query()
                ->where('tenant_id', $client->getKey())
                ->where('type', User::TYPE_OPERATOR)
                ->where('status', 'active')
                ->get()
                ->each(fn (User $u) => $u->notify(new ContactSubmissionReceivedNotification($submission)));
        } catch (Throwable $e) {
            Log::warning('Contact submission notification failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->reset(['name', 'email', 'phone', 'message']);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.public.contact-form');
    }
}
