<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\activemail;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;

/**
 * ActiveMessage represents particular mail sending process.
 * It combines the data and the logic for the particular mail content composition and sending.
 *
 * For each mail sending event, which appears in the application, the child class of ActiveMessage
 * should be created:
 * <code>
 * namespace app\mail\ar;
 *
 * use yii2tech\activemail\ActiveMessage;
 * use Yii;
 *
 * class ContactUs extends ActiveMessage
 * {
 *     public function defaultFrom()
 *     {
 *         return Yii::$app->params['applicationEmail'];
 *     }
 *
 *     public function defaultTo()
 *     {
 *         return Yii::$app->params->mail['adminEmail'];
 *     }
 *
 *     public function defaultSubject()
 *     {
 *         return 'Contact message on ' . Yii::$app->name;
 *     }
 *
 *     public function defaultBodyHtml()
 *     {
 *         return 'Contact message';
 *     }
 * }
 * </code>
 *
 * Once message created and populated it can be sent via [[send()]] method.
 *
 * ActiveMessage supports using of the mail templates provided by [[yii2tech\activemail\TemplateStorage]].
 *
 * @see yii2tech\activemail\TemplateStorage
 *
 * @property mixed $from public alias of {@link _from}
 * @property mixed $to public alias of {@link _to}
 * @property mixed $subject public alias of {@link _subject}
 * @property mixed $bodyText public alias of {@link _bodyText}
 * @property mixed $bodyHtml public alias of {@link _bodyHtml}
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
abstract class ActiveMessage extends Model
{
    /**
     * @event Event an event that is triggered before message is sent.
     */
    const EVENT_BEFORE_SEND = 'beforeSend';

    /**
     * @var mixed message sender.
     */
    private $_from;
    /**
     * @var mixed message receiver(s).
     */
    private $_to;
    /**
     * @var string message subject
     */
    private $_subject;
    /**
     * @var string message plain text body
     */
    private $_bodyText;
    /**
     * @var string message HTML body
     */
    private $_bodyHtml;

    /**
     * @param mixed $from
     */
    public function setFrom($from)
    {
        $this->_from = $from;
    }

    /**
     * @return mixed
     */
    public function getFrom()
    {
        if (empty($this->_from)) {
            $this->_from = $this->defaultFrom();
        }
        return $this->_from;
    }

    /**
     * @param mixed $to
     */
    public function setTo($to)
    {
        $this->_to = $to;
    }

    /**
     * @return mixed
     */
    public function getTo()
    {
        if (empty($this->_to)) {
            $this->_to = $this->defaultTo();
        }
        return $this->_to;
    }

    /**
     * @param string $subject
     */
    public function setSubject($subject)
    {
        $this->_subject = $subject;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        if (empty($this->_subject)) {
            $this->_subject = $this->defaultSubject();
        }
        return $this->_subject;
    }

    /**
     * @param string $bodyHtml
     */
    public function setBodyHtml($bodyHtml)
    {
        $this->_bodyHtml = $bodyHtml;
    }

    /**
     * @return string
     */
    public function getBodyHtml()
    {
        if (empty($this->_bodyHtml)) {
            $this->_bodyHtml = $this->defaultBodyHtml();
        }
        return $this->_bodyHtml;
    }

    /**
     * @param string $bodyText
     */
    public function setBodyText($bodyText)
    {
        $this->_bodyText = $bodyText;
    }

    /**
     * @return string
     */
    public function getBodyText()
    {
        if (empty($this->_bodyText)) {
            $this->_bodyText = $this->defaultBodyText();
        }
        return $this->_bodyText;
    }

    /**
     * @return \yii\mail\MailerInterface mailer instance.
     */
    public function getMailer()
    {
        return Yii::$app->getMailer();
    }

    /**
     * @return TemplateStorage template storage instance.
     */
    public function getTemplateStorage()
    {
        return Yii::$app->get('mailTemplateStorage');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [$this->attributes(), 'required'],
        ];
    }

    /**
     * @return string default sender
     */
    abstract public function defaultFrom();

    /**
     * @return string default receiver
     */
    abstract public function defaultTo();

    /**
     * @return string default subject
     */
    abstract public function defaultSubject();

    /**
     * @return string default HTML body
     */
    abstract public function defaultBodyHtml();

    /**
     * @return string default plain text body
     */
    public function defaultBodyText()
    {
        return 'You need email client with HTML support to view this message.';
    }

    /**
     * @return string message view name.
     */
    public function viewName()
    {
        return 'activeMessage';
    }

    /**
     * @return string message template name.
     */
    public function templateName()
    {
        $className = get_class($this);
        if (($pos = mb_strrpos($className, '\\')) !== false) {
            return mb_substr($className, $pos + 1);
        }
        return $className;
    }

    /**
     * Returns the hints for template data.
     * Hints are can be used, while composing edit form for the mail template.
     * @return array template data hints in format: (name => hint)
     */
    public function templateDataHints()
    {
        return [];
    }

    /**
     * Returns all this model error messages as single summary string.
     * @param string $glue messages separator.
     * @return string error summary.
     */
    public function getErrorSummary($glue = "\n")
    {
        $errors = $this->getErrors();
        $summaryParts = [];
        foreach ($errors as $attributeErrors) {
            $summaryParts = array_merge($summaryParts, $attributeErrors);
        }
        return implode($glue, $summaryParts);
    }

    /**
     * Parses template string.
     * @param string $template template string.
     * @param array $data parsing data.
     * @return string parsing result.
     */
    protected function parseTemplate($template, array $data = [])
    {
        $replacePairs = [];
        foreach ($data as $name => $value) {
            $replacePairs['{' . $name . '}'] = $value;
        }
        return strtr($template, $replacePairs);
    }

    /**
     * Sends this message
     * @param boolean $runValidation whether to perform validation before sending the message.
     * @return boolean success.
     * @throws InvalidConfigException on failure
     */
    public function send($runValidation = true)
    {
        if ($runValidation && !$this->validate()) {
            throw new InvalidConfigException('Unable to send message: ' . $this->getErrorSummary());
        }
        $data = $this->composeTemplateData();

        //$this->beforeCompose($mailMessage, $data);

        $this->applyTemplate();
        $this->applyParse($data);

        $data['activeMessage'] = $this;

        $mailMessage = $this->getMailer()
            ->compose($this->viewName(), $data)
            ->setSubject($this->getSubject())
            ->setTo($this->getTo())
            ->setFrom($this->getFrom())
            ->setReplyTo($this->getFrom());

        if ($this->beforeSend($mailMessage)) {
            return $this->getMailer()->send($mailMessage);
        } else {
            return false;
        }
    }

    /**
     * Composes data, which should be used to parse template.
     * By default this method returns all current message model attributes.
     * Child classes may override this method to customize template data.
     * @return array data to be passed to templates.
     */
    protected function composeTemplateData()
    {
        return $this->getAttributes();
    }

    /**
     * Applies corresponding template to the message if it exist.
     */
    protected function applyTemplate()
    {
        $templateAttributes = $this->getTemplateStorage()->getTemplate($this->templateName());
        if (!empty($templateAttributes)) {
            foreach ($templateAttributes as $name => $value) {
                $setter = 'set' . $name;
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                } else {
                    $this->$name = $value;
                }
            }
        }
    }

    /**
     * Applies parsing to this message internal fields.
     * @param array $data template parse data.
     */
    protected function applyParse(array $data)
    {
        $propertyNames = [
            'subject',
            'bodyText',
            'bodyHtml',
            'bodyHtml',
        ];
        foreach ($propertyNames as $propertyName) {
            $getter = 'get' . $propertyName;
            $setter = 'set' . $propertyName;
            $content = $this->$getter();
            $content = $this->parseTemplate($content, $data);
            $this->$setter($content);
        }
    }

    // Events :

    /**
     * This method is invoked before mail message sending.
     * The default implementation raises a `beforeSend` event.
     * You may override this method to do preliminary checks or adjustments before sending.
     * Make sure the parent implementation is invoked so that the event can be raised.
     * @param \yii\mail\MessageInterface $mailMessage mail message instance.
     * @return boolean whether message should be sent. Defaults to true.
     * If false is returned, no message sending will be performed.
     */
    protected function beforeSend($mailMessage)
    {
        $event = new ActiveMessageEvent(['mailMessage' => $mailMessage]);
        $this->trigger(self::EVENT_BEFORE_SEND, $event);
        return $event->isValid;
    }
}