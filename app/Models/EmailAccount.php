<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use App\Enums\EmailProvider;
use App\Enums\MailEncryption;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailAccount extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'user_id', 'provider', 'email_address', 'display_name',
        'smtp_host', 'smtp_port', 'smtp_encryption',
        'imap_host', 'imap_port', 'imap_encryption',
        'username', 'password',
        'connection_status', 'connection_error', 'last_tested_at', 'last_sync_at',
        'signature', 'is_default', 'is_active',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'provider' => EmailProvider::class,
            'smtp_encryption' => MailEncryption::class,
            'imap_encryption' => MailEncryption::class,
            'connection_status' => ConnectionStatus::class,
            // AES-256-CBC via the app's APP_KEY (Laravel's default Encrypter).
            // Rotating APP_KEY makes every stored password here permanently
            // undecryptable — no mitigation built for that in this phase.
            'password' => 'encrypted',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
