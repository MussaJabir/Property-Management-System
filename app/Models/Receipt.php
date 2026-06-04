<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Receipt = the proof-of-payment record issued to a renter when a payment
 * reaches `completed`. One-to-one with Payment. PaymentObserver creates the
 * row; ReceiptPdfGenerator fills pdf_path on first download/send.
 *
 * No soft deletes — a receipt is either issued or it isn't.
 *
 * @property string $receipt_number
 * @property string|null $pdf_path
 * @property Carbon $issued_at
 * @property Carbon|null $sent_via_email_at
 * @property Carbon|null $sent_via_whatsapp_at
 * @property-read Payment|null $payment
 */
class Receipt extends Model
{
    use HasFactory, TenantScopedModel;

    protected $fillable = [
        'payment_id',
        'receipt_number',
        'pdf_path',
        'issued_at',
        'sent_via_email_at',
        'sent_via_whatsapp_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'sent_via_email_at' => 'datetime',
            'sent_via_whatsapp_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
