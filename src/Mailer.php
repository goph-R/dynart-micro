<?php

namespace Dynart\Micro;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer {

    const CONFIG_FAKE = 'mailer.fake';
    const CONFIG_SMTP_AUTH = 'mailer.smtp_auth';
    const CONFIG_DEBUG_LEVEL = 'mailer.debug_level';
    const CONFIG_VERIFY_SSL = 'mailer.verify_ssl';
    const CONFIG_HOST = 'mailer.host';
    const CONFIG_USERNAME = 'mailer.username';
    const CONFIG_PASSWORD = 'mailer.password';
    const CONFIG_PORT = 'mailer.port';
    const CONFIG_SMTP_SECURE = 'mailer.smtp_secure';
    const CONFIG_CHARSET = 'mailer.charset';
    const CONFIG_ENCODING = 'mailer.encoding';
    const CONFIG_FROM_NAME = 'mailer.from_name';
    const CONFIG_FROM_EMAIL = 'mailer.from_email';

    const DEFAULT_SMTP_AUTH = true;
    const DEFAULT_SMTP_SECURE = 'ssl';
    const DEFAULT_CHARSET = 'UTF-8';
    const DEFAULT_ENCODING = 'quoted-printable';


    /** @var Config */
    private $config;

    /** @var Logger */
    private $logger;

    /** @var View */
    private $view;

    private $addresses = [];
    private $vars = [];

    public function __construct(Config $config, Logger $logger, View $view) {
        $this->config = $config;
        $this->logger = $logger;
        $this->view = $view;
    }

    public function init() {
        $this->addresses = [];
    }

    public function addAddress($email, $name = null) {
        $this->addresses[] = [
            'email' => $email,
            'name' => $name
        ];
    }

    public function set($name, $value) {
        $this->vars[$name] = $value;
    }

    public function send($subject, $templatePath, $vars = []) {
        $body = $this->view->fetch($templatePath, array_merge($this->vars, $vars));
        $result = true;
        if ($this->config->get(self::CONFIG_FAKE)) {
            $this->fakeSend($subject, $body);
        } else {
            $result = $this->realSend($subject, $body);
        }
        return $result;
    }

    private function fakeSend($subject, $body) {
        $to = [];
        foreach ($this->addresses as $address) {
            $to[] = $address['name'].' <'.$address['email'].'>';
        }
        $message = "Fake mail sent\n";
        $message .= 'To: '.join('; ', $to)."\n";
        $message .= 'Subject: '.$subject."\n";
        $message .= "Message:\n".$body."\n";
        $this->logger->info($message);
        return true;
    }

    private function usingSmtpAuth() {
        return $this->config->get(self::CONFIG_SMTP_AUTH, self::DEFAULT_SMTP_AUTH);
    }

    private function getDebugLevel() {
        return (int)$this->config->get(self::CONFIG_DEBUG_LEVEL);
    }

    private function realSend($subject, $body) {
        $result = true;
        $mail = new PHPMailer(true);
        $mail->SMTPAuth = $this->usingSmtpAuth();
        $mail->SMTPDebug = $this->getDebugLevel();
        $mail->Debugoutput = [$this, 'debugOutput'];
        if (!$this->config->get(self::CONFIG_VERIFY_SSL)) {
            $this->disableVerify($mail);
        }
        try {
            $this->setDefaults($mail);
            $this->addAddresses($mail);
            $mail->Subject = '=?utf-8?Q?'.quoted_printable_encode($subject).'?=';
            $mail->Body = $body;
            $mail->send();
        } catch (PHPMailerException $e) {
            $this->logger->error($e->getMessage());
            $result = false;
        }
        return $result;
    }

    private function disableVerify(PHPMailer $mail) {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
    }

    /**
     * @param PHPMailer $mail
     * @throws PHPMailerException
     */
    private function setDefaults(PHPMailer $mail) {
        $mail->isHTML(true);
        $mail->isSMTP();
        $mail->Host = $this->config->get(self::CONFIG_HOST);
        $mail->SMTPAuth = true;
        $mail->Username = $this->config->get(self::CONFIG_USERNAME);
        $mail->Password = $this->config->get(self::CONFIG_PASSWORD);
        $mail->SMTPSecure = $this->config->get(self::CONFIG_SMTP_SECURE, self::DEFAULT_SMTP_SECURE);
        $mail->Port = $this->config->get(self::CONFIG_PORT);
        $mail->Encoding = $this->config->get(self::CONFIG_ENCODING, self::DEFAULT_ENCODING);
        $mail->CharSet = $this->config->get(self::CONFIG_CHARSET, self::DEFAULT_CHARSET);
        $mail->setFrom($this->config->get(self::CONFIG_FROM_EMAIL), $this->config->get(self::CONFIG_FROM_NAME));
    }

    /**
     * @param PHPMailer $mail
     * @throws PHPMailerException
     */
    private function addAddresses(PHPMailer $mail) {
        foreach ($this->addresses as $address) {
            $mail->addAddress($address['email'], $address['name']);
        }
    }

}