<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\ContactSubmission;
use App\Models\User;
use App\Notifications\ContactSubmissionReceivedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class ContactForm extends Component
{
    /** Valid submissions allowed per client+IP before throttling. */
    private const MAX_SUBMISSIONS = 5;

    /** Window (seconds) the submission count decays over. */
    private const DECAY_SECONDS = 600;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    /**
     * Honeypot — hidden from humans via CSS. Bots fill it; those submissions
     * are dropped silently. Deliberately NOT #[Locked] (it's a real input).
     */
    public string $website = '';

    public bool $sent = false;

    /**
     * Tenant key captured on page load — this public form's Livewire submit
     * hits a central route where path-based tenancy isn't re-resolved.
     */
    #[Locked]
    public string $clientKey = '';

    public function mount(): void
    {
        $this->clientKey = tenant()?->getKey() ?? '';
    }

    public function submit(): void
    {
        // Honeypot: a hidden field real users never see. Bots fill it — drop
        // the submission silently (fake success so we don't teach evasion).
        if ($this->website !== '') {
            $this->reset(['name', 'email', 'phone', 'message']);
            $this->sent = true;

            return;
        }

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

        // Throttle genuine submissions per client + IP to blunt spam floods
        // (each submission also fans out an email to every operator).
        $throttleKey = 'contact-form:'.$this->clientKey.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_SUBMISSIONS)) {
            $this->addError('message', __('You have sent several messages recently. Please try again in a little while.'));

            return;
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        $submission = new ContactSubmission([
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'message' => $this->message,
            'status' => ContactSubmission::STATUS_NEW,
            'ip' => request()->ip(),
        ]);
        $submission->tenant_id = $this->clientKey;
        $submission->save();

        // Notify every active operator on this client. Failure to email
        // shouldn't lose the submission — it's already in the DB.
        try {
            User::query()
                ->where('tenant_id', $this->clientKey)
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
