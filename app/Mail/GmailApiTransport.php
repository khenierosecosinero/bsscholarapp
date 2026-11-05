<?php

namespace App\Mail;

use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message as GmailMessage;
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
                    ? ($token['error'] ?? 'unknown_error')
                    : 'unknown_error';

                throw new TransportException(
                    'Gmail API OAuth token refresh failed: ' . $error
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
            throw new TransportException(
                'Gmail API email delivery failed. Check OAuth authorization and Gmail API access.',
                0,
                $e
            );
        }
    }

    public function __toString(): string
    {
        return 'gmail_api';
    }
}