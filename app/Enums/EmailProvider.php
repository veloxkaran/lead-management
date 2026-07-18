<?php

namespace App\Enums;

enum EmailProvider: string
{
    case Gmail = 'gmail';
    case Microsoft365 = 'microsoft_365';
    case Zoho = 'zoho';
    case CustomSmtp = 'custom_smtp';
    case CompanyMailServer = 'company_mail_server';

    public function label(): string
    {
        return match ($this) {
            self::Gmail => 'Gmail',
            self::Microsoft365 => 'Microsoft 365 / Outlook',
            self::Zoho => 'Zoho Mail',
            self::CustomSmtp => 'Custom SMTP / IMAP',
            self::CompanyMailServer => 'Company Mail Server',
        };
    }

    public function defaultSmtpHost(): ?string
    {
        return match ($this) {
            self::Gmail => 'smtp.gmail.com',
            self::Microsoft365 => 'smtp.office365.com',
            self::Zoho => 'smtp.zoho.com',
            self::CustomSmtp, self::CompanyMailServer => null,
        };
    }

    public function defaultSmtpPort(): ?int
    {
        return match ($this) {
            self::Gmail, self::Microsoft365, self::Zoho => 587,
            self::CustomSmtp, self::CompanyMailServer => null,
        };
    }

    public function defaultSmtpEncryption(): ?MailEncryption
    {
        return match ($this) {
            self::Gmail, self::Microsoft365, self::Zoho => MailEncryption::Tls,
            self::CustomSmtp, self::CompanyMailServer => null,
        };
    }

    public function defaultImapHost(): ?string
    {
        return match ($this) {
            self::Gmail => 'imap.gmail.com',
            self::Microsoft365 => 'outlook.office365.com',
            self::Zoho => 'imap.zoho.com',
            self::CustomSmtp, self::CompanyMailServer => null,
        };
    }

    public function defaultImapPort(): ?int
    {
        return match ($this) {
            self::Gmail, self::Microsoft365, self::Zoho => 993,
            self::CustomSmtp, self::CompanyMailServer => null,
        };
    }

    public function defaultImapEncryption(): ?MailEncryption
    {
        return match ($this) {
            self::Gmail, self::Microsoft365, self::Zoho => MailEncryption::Ssl,
            self::CustomSmtp, self::CompanyMailServer => null,
        };
    }
}
