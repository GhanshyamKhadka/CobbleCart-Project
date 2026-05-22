<?php
// Minimal SMTP client for Gmail (and any SMTP server supporting STARTTLS + AUTH LOGIN).
// No external dependencies. Drop-in alternative to PHPMailer for the small needs of this app.
//
// Usage:
//   $mailer = new SmtpMailer($config);   // $config from mail-config.local.php
//   $mailer->send('to@example.com', 'Display Name', 'Subject', "Plain-text body");
//
// Throws RuntimeException on any protocol failure. Caller decides whether to surface or log.

class SmtpMailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private int $timeout;
    /** @var resource|null */
    private $socket = null;

    public function __construct(array $config)
    {
        foreach (['host', 'port', 'username', 'password', 'from_email', 'from_name'] as $required) {
            if (empty($config[$required])) {
                throw new RuntimeException("SmtpMailer: missing config key '$required'");
            }
        }
        $this->host = $config['host'];
        $this->port = (int)$config['port'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->fromEmail = $config['from_email'];
        $this->fromName = $config['from_name'];
        $this->timeout = (int)($config['timeout'] ?? 15);
    }

    public function send(string $toEmail, string $toName, string $subject, string $body): void
    {
        $this->connect();
        try {
            $this->expect(220);
            $this->cmd('EHLO ' . $this->ehloHost(), 250);
            $this->cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SmtpMailer: STARTTLS upgrade failed');
            }
            $this->cmd('EHLO ' . $this->ehloHost(), 250);
            $this->cmd('AUTH LOGIN', 334);
            $this->cmd(base64_encode($this->username), 334);
            $this->cmd(base64_encode($this->password), 235);
            $this->cmd('MAIL FROM:<' . $this->fromEmail . '>', 250);
            $this->cmd('RCPT TO:<' . $toEmail . '>', 250);
            $this->cmd('DATA', 354);
            fwrite($this->socket, $this->buildMessage($toEmail, $toName, $subject, $body));
            $this->expect(250);
            $this->cmd('QUIT', 221);
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        $errno = 0; $errstr = '';
        $socket = @stream_socket_client(
            'tcp://' . $this->host . ':' . $this->port,
            $errno, $errstr, $this->timeout
        );
        if ($socket === false) {
            throw new RuntimeException("SmtpMailer: connect to {$this->host}:{$this->port} failed — $errstr ($errno)");
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }

    private function cmd(string $line, int $expectedCode): string
    {
        fwrite($this->socket, $line . "\r\n");
        return $this->expect($expectedCode);
    }

    private function expect(int $expectedCode): string
    {
        $response = '';
        // SMTP replies can be multi-line: "250-foo\r\n250 bar\r\n"
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SmtpMailer: server closed connection unexpectedly');
            }
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr(ltrim($response), 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException("SmtpMailer: expected $expectedCode, got: " . trim($response));
        }
        return $response;
    }

    private function ehloHost(): string
    {
        $host = gethostname();
        return $host ?: 'localhost';
    }

    private function buildMessage(string $toEmail, string $toName, string $subject, string $body): string
    {
        $boundary = '----=_Part_' . bin2hex(random_bytes(8));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = sprintf('"%s" <%s>', $this->encodeName($this->fromName), $this->fromEmail);
        $toHeader   = $toName !== '' ? sprintf('"%s" <%s>', $this->encodeName($toName), $toEmail) : $toEmail;
        $date = date('r');

        $headers = [
            'Date: ' . $date,
            'From: ' . $fromHeader,
            'To: ' . $toHeader,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        // Body lines starting with "." must be escaped per RFC 5321 section 4.5.2
        $escapedBody = preg_replace('/^\./m', '..', $body);
        $escapedBody = str_replace("\r\n", "\n", $escapedBody);
        $escapedBody = str_replace("\n", "\r\n", $escapedBody);

        return implode("\r\n", $headers) . "\r\n\r\n" . $escapedBody . "\r\n.\r\n";
    }

    private function encodeName(string $name): string
    {
        // Quote-escape any double quotes
        return str_replace('"', '\"', $name);
    }
}
