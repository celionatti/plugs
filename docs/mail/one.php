# Mail Service - Complete Implementation Files

## 📁 File Structure

```
your-framework/
├── app/
│   └── Plugs/
│       ├── Mail/
│       │   ├── MailService.php
│       │   └── EmailBuilder.php
│       └── Facades/
│           └── Mail.php
└── config/
    └── mail.php
```

---

## 📄 File 1: `app/Plugs/Mail/MailService.php`

```php
<?php

declare(strict_types=1);

namespace Plugs\Mail;

/*
|--------------------------------------------------------------------------
| MailService Class
|--------------------------------------------------------------------------
|
| This class provides email functionality using Symfony Mailer.
| It supports simple emails, CC/BCC, attachments, and multipart messages.
*/

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MailService
{
    private Mailer $mailer;
    private string $fromEmail;
    private string $fromName;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        
        // Create DSN (Data Source Name) for transport
        $dsn = $this->buildDsn($config);
        
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
        
        $this->fromEmail = $config['from']['address'] ?? 'noreply@example.com';
        $this->fromName = $config['from']['name'] ?? 'My Application';
    }

    /**
     * Build DSN string from config
     */
    private function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'smtp';
        $encryption = $config['encryption'] ?? 'tls';
        
        // Handle encryption in DSN
        if ($encryption === 'ssl') {
            $driver = $driver . 's';
        }
        
        $dsn = sprintf(
            '%s://%s:%s@%s:%s',
            $driver,
            urlencode($config['username'] ?? ''),
            urlencode($config['password'] ?? ''),
            $config['host'] ?? 'localhost',
            $config['port'] ?? 587
        );

        // Add TLS option if needed
        if ($encryption === 'tls') {
            $dsn .= '?encryption=tls';
        }

        return $dsn;
    }

    /**
     * Send a simple email
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject);

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email to multiple recipients
     */
    public function sendToMultiple(array $recipients, string $subject, string $body, bool $isHtml = true): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->subject($subject);

            foreach ($recipients as $recipient) {
                $email->addTo($recipient);
            }

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email with CC and BCC
     */
    public function sendWithCopies(
        string $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        bool $isHtml = true
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject);

            foreach ($cc as $ccEmail) {
                $email->cc($ccEmail);
            }

            foreach ($bcc as $bccEmail) {
                $email->bcc($bccEmail);
            }

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email with attachments
     */
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        bool $isHtml = true
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject);

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $email->attachFromPath($attachment);
                }
            }

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email with inline attachments (embedded images)
     */
    public function sendWithEmbeddedImage(
        string $to,
        string $subject,
        string $body,
        array $embedImages = []
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject)
                ->html($body);

            foreach ($embedImages as $cid => $path) {
                if (file_exists($path)) {
                    $email->embedFromPath($path, $cid);
                }
            }

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email with both HTML and plain text versions
     */
    public function sendMultipart(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject)
                ->html($htmlBody)
                ->text($textBody);

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email with custom reply-to address
     */
    public function sendWithReplyTo(
        string $to,
        string $subject,
        string $body,
        string $replyTo,
        string $replyToName = '',
        bool $isHtml = true
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->replyTo(new Address($replyTo, $replyToName))
                ->subject($subject);

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a fluent email builder
     */
    public function createEmail(): EmailBuilder
    {
        return new EmailBuilder($this->mailer, $this->fromEmail, $this->fromName);
    }
}
```

---

## 📄 File 2: `app/Plugs/Mail/EmailBuilder.php`

```php
<?php

declare(strict_types=1);

namespace Plugs\Mail;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailBuilder
{
    private Mailer $mailer;
    private Email $email;

    public function __construct(Mailer $mailer, string $fromEmail, string $fromName)
    {
        $this->mailer = $mailer;
        $this->email = (new Email())->from(new Address($fromEmail, $fromName));
    }

    public function to(string $email, string $name = ''): self
    {
        $this->email->to(new Address($email, $name));
        return $this;
    }

    public function cc(string $email, string $name = ''): self
    {
        $this->email->cc(new Address($email, $name));
        return $this;
    }

    public function bcc(string $email, string $name = ''): self
    {
        $this->email->bcc(new Address($email, $name));
        return $this;
    }

    public function replyTo(string $email, string $name = ''): self
    {
        $this->email->replyTo(new Address($email, $name));
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->email->subject($subject);
        return $this;
    }

    public function html(string $body): self
    {
        $this->email->html($body);
        return $this;
    }

    public function text(string $body): self
    {
        $this->email->text($body);
        return $this;
    }

    public function attach(string $path, string $name = null): self
    {
        if (file_exists($path)) {
            $this->email->attachFromPath($path, $name);
        }
        return $this;
    }

    public function embed(string $path, string $cid): self
    {
        if (file_exists($path)) {
            $this->email->embedFromPath($path, $cid);
        }
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->email->priority($priority);
        return $this;
    }

    public function send(): bool
    {
        try {
            $this->mailer->send($this->email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }
}
```

---

## 📄 File 3: `app/Plugs/Facades/Mail.php`

```php
<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Container\Container;
use Plugs\Mail\MailService;

class Mail
{
    /**
     * Get the mail service instance
     */
    private static function getMailService(): MailService
    {
        return Container::getInstance()->make('mail');
    }

    /**
     * Send a simple email
     */
    public static function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        return self::getMailService()->send($to, $subject, $body, $isHtml);
    }

    /**
     * Send email to multiple recipients
     */
    public static function sendToMultiple(array $recipients, string $subject, string $body, bool $isHtml = true): bool
    {
        return self::getMailService()->sendToMultiple($recipients, $subject, $body, $isHtml);
    }

    /**
     * Send email with CC and BCC
     */
    public static function sendWithCopies(
        string $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        bool $isHtml = true
    ): bool {
        return self::getMailService()->sendWithCopies($to, $subject, $body, $cc, $bcc, $isHtml);
    }

    /**
     * Send email with attachments
     */
    public static function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        bool $isHtml = true
    ): bool {
        return self::getMailService()->sendWithAttachment($to, $subject, $body, $attachments, $isHtml);
    }

    /**
     * Send multipart email
     */
    public static function sendMultipart(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        return self::getMailService()->sendMultipart($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Send email with reply-to
     */
    public static function sendWithReplyTo(
        string $to,
        string $subject,
        string $body,
        string $replyTo,
        string $replyToName = '',
        bool $isHtml = true
    ): bool {
        return self::getMailService()->sendWithReplyTo($to, $subject, $body, $replyTo, $replyToName, $isHtml);
    }

    /**
     * Create a fluent email builder
     */
    public static function createEmail()
    {
        return self::getMailService()->createEmail();
    }
}
```

---

## 📄 File 4: `config/mail.php`

```php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mail Configuration File
|--------------------------------------------------------------------------
|
| This file is used to configure various settings for the mail service.
*/

return [
    'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
    'host' => $_ENV['MAIL_HOST'] ?? 'smtp.mailtrap.io',
    'port' => (int)($_ENV['MAIL_PORT'] ?? 2525),
    'username' => $_ENV['MAIL_USERNAME'] ?? '',
    'password' => $_ENV['MAIL_PASSWORD'] ?? '',
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls', // tls or ssl
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'My App'
    ]
];
```

---

## 📄 File 5: `.env` (Add these lines)

```env
# Mail Configuration
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="My App"
```

---

## 🚀 Usage Examples

### Example 1: Simple Email

```php
<?php

use Plugs\Facades\Mail;

// In any controller or file
Mail::send(
    'user@example.com',
    'Welcome to Our Platform',
    '<h1>Welcome!</h1><p>Thanks for joining us.</p>'
);
```

### Example 2: Welcome Email After Registration

```php
<?php

// In your UserController.php
public function register($request)
{
    // Create user
    $user = User::create([
        'name' => $request->post('name'),
        'email' => $request->post('email'),
        'password' => password_hash($request->post('password'), PASSWORD_DEFAULT)
    ]);
    
    // Send welcome email
    $html = "
        <h1>Welcome {$user->name}!</h1>
        <p>Thank you for joining us.</p>
        <p><a href='https://yourapp.com/dashboard'>Go to Dashboard</a></p>
    ";
    
    Mail::send($user->email, 'Welcome to Our Platform', $html);
    
    return redirect('/login')->with('success', 'Registration successful!');
}
```

### Example 3: Using Fluent Builder

```php
<?php

use Plugs\Facades\Mail;

Mail::createEmail()
    ->to('customer@example.com', 'John Doe')
    ->cc('manager@example.com')
    ->subject('Order Confirmation')
    ->html('<h1>Thank you for your order!</h1>')
    ->attach(BASE_PATH . 'storage/invoices/invoice-123.pdf')
    ->send();
```

### Example 4: Password Reset

```php
<?php

public function sendPasswordReset($email, $token)
{
    $resetUrl = "https://yourapp.com/reset-password?token={$token}";
    
    $html = "
        <h2>Password Reset Request</h2>
        <p>Click the link below to reset your password:</p>
        <a href='{$resetUrl}'>Reset Password</a>
        <p>This link expires in 1 hour.</p>
    ";
    
    Mail::sendWithReplyTo(
        $email,
        'Reset Your Password',
        $html,
        'support@yourapp.com',
        'Support Team'
    );
}
```

---

## 📦 Installation Steps

1. **Install Symfony Mailer:**
   ```bash
   composer require symfony/mailer
   ```

2. **Create the directory structure:**
   ```bash
   mkdir -p app/Plugs/Mail
   mkdir -p app/Plugs/Facades
   ```

3. **Copy each file to its location** (as shown in the file structure above)

4. **Update your `.env` file** with mail credentials

5. **Your `config/services.php` already has the mail binding**, so you're good to go!

6. **Test it:**
   ```php
   <?php
   
   use Plugs\Facades\Mail;
   
   Mail::send(
       'test@example.com',
       'Test Email',
       '<h1>It works!</h1>'
   );
   ```

---

## ✅ All Done!

You now have a complete mail service with:
- ✅ Simple email sending
- ✅ Multiple recipients
- ✅ CC/BCC support
- ✅ File attachments
- ✅ Embedded images
- ✅ Reply-to functionality
- ✅ Fluent builder interface
- ✅ Facade for easy access
- ✅ Full error handling