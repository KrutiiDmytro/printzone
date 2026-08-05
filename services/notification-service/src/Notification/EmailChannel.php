<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Sends a Twig-templated email. The single delivery channel today; SMS/push are
 * introduced (behind a channel interface) once an event that needs them is wired.
 */
final class EmailChannel
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(ADMIN_EMAIL)%')]
        private readonly string $fromEmail,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function send(string $to, string $subject, string $template, array $context = []): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        $this->mailer->send($email);
    }
}
