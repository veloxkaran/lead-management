<?php

namespace App\Services;

use App\Enums\ConnectionStatus;
use App\Enums\MailEncryption;
use App\Models\EmailAccount;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Webklex\PHPIMAP\ClientManager;

/**
 * A pure I/O check with no persistence/authorization concerns of its own —
 * kept separate from EmailAccountService the same way OrganizationHierarchyService
 * stays separate from domain services. Never touches config/mail.php or any
 * global mailer/IMAP config; every transport/client is built ad-hoc from the
 * given account's own stored credentials.
 */
class EmailAccountConnectionTester
{
    /**
     * @return array{status: ConnectionStatus, error: ?string}
     */
    public function test(EmailAccount $account): array
    {
        $smtpResult = $this->testSmtp($account);

        if ($smtpResult['status'] === ConnectionStatus::Failed) {
            return $smtpResult;
        }

        if ($account->imap_host) {
            $imapResult = $this->testImap($account);

            if ($imapResult['status'] === ConnectionStatus::Failed) {
                return $imapResult;
            }
        }

        return ['status' => ConnectionStatus::Connected, 'error' => null];
    }

    /**
     * @return array{status: ConnectionStatus, error: ?string}
     */
    private function testSmtp(EmailAccount $account): array
    {
        $scheme = $account->smtp_encryption === MailEncryption::Ssl ? 'smtps' : 'smtp';
        $options = $account->smtp_encryption === MailEncryption::None ? ['auto_tls' => '0'] : [];

        $dsn = new Dsn($scheme, $account->smtp_host, $account->username, $account->password, $account->smtp_port, $options);
        $transport = (new EsmtpTransportFactory())->create($dsn);

        try {
            $transport->start();
            $transport->stop();

            return ['status' => ConnectionStatus::Connected, 'error' => null];
        } catch (TransportExceptionInterface $e) {
            return ['status' => ConnectionStatus::Failed, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: ConnectionStatus, error: ?string}
     */
    private function testImap(EmailAccount $account): array
    {
        $client = (new ClientManager())->make([
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => $account->imap_encryption === MailEncryption::None ? false : $account->imap_encryption?->value,
            'validate_cert' => true,
            'username' => $account->username,
            'password' => $account->password,
            'protocol' => 'imap',
        ]);

        try {
            $client->connect();
            $client->disconnect();

            return ['status' => ConnectionStatus::Connected, 'error' => null];
        } catch (\Throwable $e) {
            return ['status' => ConnectionStatus::Failed, 'error' => $e->getMessage()];
        }
    }
}
