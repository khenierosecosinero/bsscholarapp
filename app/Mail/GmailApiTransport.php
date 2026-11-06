<?php

namespace App\Mail;

use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail;
use Google\Service\Gmail\Message as GmailMessage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
use Throwable;

class GmailApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
        private readonly string $senderAddress
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        // Ensure OAuth credentials are configured.
        if (
            $this->clientId === '' ||
            $this->clientSecret === '' ||
            $this->refreshToken === ''
        ) {
            throw new TransportException(
                'Gmail API OAuth credentials are incomplete.'
            );
        }

        if (
            $this->senderAddress === '' ||
            ! filter_var($this->senderAddress, FILTER_VALIDATE_EMAIL)
        ) {
            throw new TransportException(
                'Gmail API sender address is missing or invalid.'
            );
        }

        // Verify that Laravel is using the intended sender.
        $email = MessageConverter::toEmail(
            $message->getOriginalMessage()
        );

        $from = $email->getFrom()[0] ?? null;

        if (
            $from === null ||
            strcasecmp(
                $from->getAddress(),
                $this->senderAddress
            ) !== 0
        ) {
            throw new TransportException(
                'Gmail API sender does not match MAIL_FROM_ADDRESS.'
            );
        }

        // Create the Google OAuth client.
        $client = new GoogleClient();

        $client->setApplicationName('BSSA Email Service');
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setScopes([Gmail::GMAIL_SEND]);

        try {
            // Exchange the refresh token for an access token.
            $token = $client->fetchAccessTokenWithRefreshToken(
                $this->refreshToken
            );

            if (
                ! is_array($token) ||
                empty($token['access_token']) ||
                isset($token['error'])
            ) {
                $error = is_array($token)
                    ? (string) ($token['error'] ?? 'unknown_error')
                    : 'unknown_error';

                Log::error('Gmail API token refresh failed.', [
                    'gmail_status' => 400,
                    'gmail_reason' => $this->safeReason($error),
                ]);

                throw new TransportException(
                    'Gmail API OAuth token refresh failed: '.$this->safeReason($error)
                    .' gmail_api_status=400 gmail_api_reason='.$this->safeReason($error)
                );
            }

            $client->setAccessToken($token);

            // Convert the complete Laravel email into Gmail API format.
            // This preserves its MIME content and attachments.
            $rawMessage = rtrim(
                strtr(
                    base64_encode($message->toString()),
                    '+/',
                    '-_'
                ),
                '='
            );

            $gmailMessage = new GmailMessage();
            $gmailMessage->setRaw($rawMessage);

            // Send through Gmail API over HTTPS.
            $gmailService = new Gmail($client);

            $gmailService->users_messages->send(
                'me',
                $gmailMessage
            );

        } catch (TransportException $e) {
            throw $e;

        } catch (Throwable $e) {
            [$status, $reason] = $this->googleFailure($e);

            Log::error('Gmail API request failed.', [
                'gmail_status' => $status,
                'gmail_reason' => $reason,
                'exception' => $e::class,
            ]);

            throw new TransportException(
                'Gmail API email delivery failed.'
                .' gmail_api_status='.$status
                .' gmail_api_reason='.$reason,
                $status,
                $e
            );
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function googleFailure(Throwable $e): array
    {
        $status = 0;
        $reason = 'unknown_error';

        if ($e instanceof GoogleServiceException) {
            $status = (int) $e->getCode();
            $errors = $e->getErrors();
            if (is_array($errors) && isset($errors[0]['reason'])) {
                $reason = $this->safeReason((string) $errors[0]['reason']);
            }
        }

        return [$status, $reason];
    }

    private function safeReason(string $reason): string
    {
        $reason = strtolower(trim($reason));
        $reason = preg_replace('/[^a-z0-9_]/', '', $reason) ?? '';

        return $reason !== '' ? $reason : 'unknown_error';
    }

    public function __toString(): string
    {
        return 'gmail_api';
    }
}