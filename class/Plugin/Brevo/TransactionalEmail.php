<?php

    namespace Wonder\Plugin\Brevo;

    use Brevo\TransactionalEmails\Requests\GetEmailEventReportRequest;
    use Brevo\TransactionalEmails\Requests\GetScheduledEmailByIdRequest;
    use Brevo\TransactionalEmails\Requests\GetSmtpReportRequest;
    use Brevo\TransactionalEmails\Requests\GetTransacEmailsListRequest;
    use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
    use Brevo\TransactionalEmails\Types\SendTransacEmailRequestAttachmentItem;
    use Brevo\TransactionalEmails\Types\SendTransacEmailRequestBccItem;
    use Brevo\TransactionalEmails\Types\SendTransacEmailRequestCcItem;
    use Brevo\TransactionalEmails\Types\SendTransacEmailRequestReplyTo;
    use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
    use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
    use DateTime;
    use DateTimeInterface;

    class TransactionalEmail extends Brevo {

        public function object()
        {

            return parent::connect()->transactionalEmails;

        }

        public function send()
        {

            return $this->object()->sendTransacEmail(new SendTransacEmailRequest($this->params), $this->opts);

        }

        public function all()
        {

            return $this->object()->getTransacEmailsList(new GetTransacEmailsListRequest($this->params), $this->opts);

        }

        public function events()
        {

            return $this->object()->getEmailEventReport(new GetEmailEventReportRequest($this->params), $this->opts);

        }

        public function report()
        {

            return $this->object()->getSmtpReport(new GetSmtpReportRequest($this->params), $this->opts);

        }

        public function get($uuid)
        {

            return $this->object()->getTransacEmailContent($uuid, $this->opts);

        }

        public function scheduled($identifier)
        {

            $params = $this->params;

            if (isset($params['startDate']) && !($params['startDate'] instanceof DateTimeInterface)) {
                $params['startDate'] = new DateTime((string) $params['startDate']);
            }

            if (isset($params['endDate']) && !($params['endDate'] instanceof DateTimeInterface)) {
                $params['endDate'] = new DateTime((string) $params['endDate']);
            }

            return $this->object()->getScheduledEmailById($identifier, new GetScheduledEmailByIdRequest($params), $this->opts);

        }

        public function delete($identifier)
        {

            return $this->object()->deleteScheduledEmailById($identifier, $this->opts);

        }

        public function sender($email, $name = null, ?int $id = null): static
        {

            return $this->addParams('sender', new SendTransacEmailRequestSender([
                'email' => $email,
                'name' => $name,
                'id' => $id
            ]));

        }

        public function senderId(int $id): static
        {

            return $this->addParams('sender', new SendTransacEmailRequestSender([
                'id' => $id
            ]));

        }

        public function to($email, $name = null): static
        {

            return $this->pushParams('to', new SendTransacEmailRequestToItem([
                'email' => $email,
                'name' => $name
            ]));

        }

        public function cc($email, $name = null): static
        {

            return $this->pushParams('cc', new SendTransacEmailRequestCcItem([
                'email' => $email,
                'name' => $name
            ]));

        }

        public function bcc($email, $name = null): static
        {

            return $this->pushParams('bcc', new SendTransacEmailRequestBccItem([
                'email' => $email,
                'name' => $name
            ]));

        }

        public function replyTo($email, $name = null): static
        {

            return $this->addParams('replyTo', new SendTransacEmailRequestReplyTo([
                'email' => $email,
                'name' => $name
            ]));

        }

        public function attachmentContent($name, $content): static
        {

            return $this->pushParams('attachment', new SendTransacEmailRequestAttachmentItem([
                'name' => $name,
                'content' => $content
            ]));

        }

        public function attachmentUrl($url, $name = null): static
        {

            return $this->pushParams('attachment', new SendTransacEmailRequestAttachmentItem([
                'name' => $name,
                'url' => $url
            ]));

        }

        public function subject($value): static
        {

            return $this->addParams('subject', $value);

        }

        public function html($value): static
        {

            return $this->addParams('htmlContent', $value);

        }

        public function text($value): static
        {

            return $this->addParams('textContent', $value);

        }

        public function templateId(int $value): static
        {

            return $this->addParams('templateId', $value);

        }

        public function param(string $key, $value): static
        {

            return $this->addParams("params.$key", $value);

        }

        public function header(string $key, $value): static
        {

            return $this->addParams("headers.$key", $value);

        }

        public function tag($value): static
        {

            return $this->pushParams('tags', $value);

        }

        public function tags(array|string $value): static
        {

            return $this->addParams('tags', $value);

        }

        public function batchId(string $value): static
        {

            return $this->addParams('batchId', $value);

        }

        public function scheduledAt(DateTimeInterface|string $value): static
        {

            if (!($value instanceof DateTimeInterface)) {
                $value = new DateTime((string) $value);
            }

            return $this->addParams('scheduledAt', $value);

        }

        public function email($value): static
        {

            return $this->addParams('email', $value);

        }

        public function messageId($value): static
        {

            return $this->addParams('messageId', $value);

        }

        public function startDate($value): static
        {

            return $this->addParams('startDate', $value);

        }

        public function endDate($value): static
        {

            return $this->addParams('endDate', $value);

        }

        public function days(int $value): static
        {

            return $this->addParams('days', $value);

        }

        public function event($value): static
        {

            return $this->addParams('event', $value);

        }

        public function sort($value): static
        {

            return $this->addParams('sort', $value);

        }

        public function limit(int $value): static
        {

            return $this->addParams('limit', $value);

        }

        public function offset(int $value): static
        {

            return $this->addParams('offset', $value);

        }

        public function tagReport($value): static
        {

            return $this->addParams('tag', $value);

        }

    }
