<?php
/**
 * Emailer class file.
 * @author Christoffer Niska <christoffer.niska@nordsoftware.com>
 * @copyright Copyright &copy; Nord Software 2013-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package vendor.nordsoftware.emailer.components
 */

/**
 * Application component for creating and sending emails.
 */
class Emailer extends CApplicationComponent {
	// Swift Mailer transport types.
	const TRANSPORT_PHP = 'php';
	const TRANSPORT_SMTP = 'smtp';

	// Mime content types.
	const CONTENT_PLAIN = 'text/plain';
	const CONTENT_HTML = 'text/html';

	/**
	 * @var string the transport type, valid values are 'php' and 'smtp'.
	 * Defaults to 'php'.
	 */
	public $transportType = self::TRANSPORT_PHP;
	/**
	 * @var array the email template configuration (name=>config).
	 */
	public $templates = array();
	/**
	 * @var string the mail options for the mailer.
	 * @see http://swiftmailer.org/docs/sending.html
	 */
	public $mailOptions;
	/**
	 * @var array the smtp options for the mailer.
	 * @see http://swiftmailer.org/docs/sending.html
	 */
	public $smtpOptions = array();
	/**
	 * @var string the path alias for where the email views are located.
	 */
	public $viewPath = 'application.views.email';
	/**
	 * @var string the path to the default layout file. Setting this to false means that no layout will be used.
	 */
	public $defaultLayout = false;
	/**
	 * @var array global data that is passed to all email templates.
	 */
	public $data = array();
	/**
	 * @var string the default character set.
	 */
	public $charset = 'utf8';
	/**
	 * @var string the controller class to use when sending emails from the c
	 */
	public $controller = 'CController';
	/**
	 * @var string the logging category.
	 */
	public $logCategory = 'vendor.nordsoftware.yii-emailer.components.Emailer';
	/**
	 * @var boolean whether the enable logging.
	 */
	public $logging = true;
	/**
	 * @var array the email filters that specify which email addresses are allowed to receive emails.
	 */
	public $emailFilters = array();
	/**
	 * @var string email address that will receive all emails sent through the emailer.
	 */
	public $catchAllEmail;
	/**
	 * @var boolean whether to prevent the actual sending of emails.
	 */
	public $dryRun = false;

	protected $_mailer;
	protected $_failedRecipients = array();

	/**
	 * Initializes the component.
	 */
	public function init() {
		parent::init();
		Yii::setPathOfAlias('emailer', __DIR__ . '/..');
		Yii::import('emailer.models.EmailMessage', true/* force include */);
	}

	/**
	 * Creates an email message from a template.
	 * @param string $name the template name.
	 * @param array $config the email configuration.
	 * @return EmailMessage the model.
 	 * @throws CException if required configuration parameters are missing.
	 */
	public function createFromTemplate($name, $config = array()) {
		if (!isset($this->templates[$name])) {
			throw new CException('Email template `' . $name . '` not found.');
		}

		$config = CMap::mergeArray($this->templates[$name], $config);

		if (!isset($config['from'])) {
			throw new CException('Configuration must contain a `from` property.');
		}
		if (!isset($config['to'])) {
			throw new CException('Configuration must contain a `to` property.');
		}
		if (!isset($config['subject'])) {
			throw new CException('Configuration must contain a `subject` property.');
		}

		return $this->create($config['from'], $config['to'], $config['subject'], $config);
	}

	/**
	 * Creates an email message.
	 * @param mixed $from the sender email address(es).
	 * @param mixed $to the recipient email address(es).
	 * @param string $subject the subject text.
	 * @param array $config the email configuration.
	 * @return EmailMessage the model.
	 * @throws CException if required configuration parameters are missing.
	 */
	public function create($from, $to, $subject, $config = array()) {
		$data = array_merge(isset($config['data']) ? $config['data'] : array(), $this->data);
		$replacePairs = array();
		foreach ($data as $from => $to) {
			$replacePairs['{' . $from . '}'] = $to;
		}
		$subject = strtr($subject, $replacePairs);
		if (isset($config['body'])) {
			$body = $config['body'];
		} else if (isset($config['view'])) {
			$view = $config['view'];
			$controller = Yii::app()->getController();
			if ($controller === null) {
				// todo: include a console controller in this extension.
				$controller = new $this->controller('email')/* for console */;
			}
			if (strpos('.', $view) === false) {
				$view = $this->viewPath . '.' . $view;
			}
			$layout = isset($config['layout']) ? $config['layout'] : $this->defaultLayout;
			if ($layout !== false) {
				$l = $controller->layout;
				$controller->layout = $layout;
				$controller->pageTitle = $subject;
				$body = $controller->render($view, $data, true);
				$controller->layout = $l;
			} else {
				$body = $controller->renderPartial($view, $data, true);
			}
		} else {
			throw new CException('Configuration must contain either a `body` or a `view` property.');
		}
		unset($config['body'], $config['view']);

		return $this->createMessage($from, $to, $subject, $body, $config);
	}

	/**
	 * Sends a single email.
	 * @param EmailMessage $model the model instance.
	 * @return integer the number of recipients.
	 */
	public function send(EmailMessage $model) {
		if ($this->logging) {
			$this->log(__CLASS__ . '.' . __FUNCTION__ . ':' . $model);
		}
		if (!$this->allowEmail($model->to)) {
			return -1; // not allowed to send
		}
		if ($this->dryRun) {
			return $model->getRecipientCount();
		}
		if (isset($this->catchAllEmail)) {
			$model->to = $this->catchAllEmail;
			$model->cc = null;
			$model->bcc = null;
		}
		$recipientCount = $this->getMailer()->send($model->createMessage(), $this->_failedRecipients);
		$model->sentTime = date('Y-m-d H:i:s');
		$model->save(false);
		return $recipientCount;
	}

	/**
	 * Creates a url to view an email message in the browser.
	 * Override this method to implement your own logic for rendering emails.
	 * @param EmailMessage $model the message model.
	 * @return string the url.
	 */
	public function createViewUrl($model) {
		return Yii::app()->createUrl('/email/view', array('id' => $model->id));
	}

	/**
	 * Checks to see if the email address is allowed.
	 * @param string  $email the email address.
	 * @return boolean whether the email is allowed.
	 */
	protected function allowEmail($email) {
		if (empty($this->emailFilters)) {
			return true;
		}
		foreach ($this->emailFilters as $filter) {
			if ($filter === '*' || $filter === $email || (($pos = strpos($filter, '*')) !== false && strpos($email, substr($filter, $pos + 1)) !== false)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Logs the given email using Yii::log().
	 * @param string $message the message to log.
	 * @param string $level the log level.
	 */
	protected function log($message, $level = CLogger::LEVEL_INFO) {
		Yii::log($message, $level, $this->logCategory);
	}

	/**
	 * Creates an email message.
	 * @param mixed $from the sender email address(es).
	 * @param mixed $to the recipient email address(es).
	 * @param string $subject the subject text.
	 * @param string $body the body text.
	 * @param array $config the email configuration.
	 * @return EmailMessage the model.
	 */
	protected function createMessage($from, $to, $subject, $body, $config = array()) {
		$message = Swift_Message::newInstance();

		// Determine content type and character set.
		$contentType = isset($config['contentType']) ? $config['contentType'] : self::CONTENT_HTML;
		$charset = isset($config['charset']) ? $config['charset'] : $this->charset;

		$message->setFrom($from)
			->setTo($to)
			->setSubject($subject)
			->setBody($body, $contentType, $charset);

		// Set cc and bcc if applicable.
		if (isset($config['cc'])) {
			$message->setCc($config['cc']);
		}
		if (isset($config['bcc'])) {
			$message->setBcc($config['bcc']);
		}

		return $this->createModel($message, $contentType, $charset);
	}

	/**
	 * Creates a model from the given Swift_Message instance and saves it.
	 * @param Swift_Message $message
	 * @param string $contentType
	 * @param string $charset
	 * @return EmailMessage the model.
	 */
	protected function createModel(Swift_Message $message, $contentType, $charset) {
		$model = new EmailMessage;
		$model->from = implode(', ', array_keys($message->getFrom()));
		$model->to = implode(', ', array_keys($message->getTo()));
		$cc = $message->getCc();
		if (is_array($cc)) {
			$model->cc = implode(', ', $cc);
		}
		$bcc = $message->getBcc();
		if (is_array($bcc)) {
			$model->bcc = implode(', ', $bcc);
		}
		$model->subject = $message->getSubject();
		$model->body = $message->getBody();
		$model->headers = implode("\n", $message->getHeaders()->getAll());
		$model->contentType = $contentType;
		$model->charset = $charset;
		$model->save(false); // need to save the model to get its id.
		$model->body = str_replace('{viewUrl}', $this->createViewUrl($model), $model->body);
		$model->save(false);
		return $model;
	}

	/**
	 * Creates the transport instance.
	 * @return Swift_Transport the instance.
	 */
	protected function createTransport() {
		switch ($this->transportType) {
			case self::TRANSPORT_SMTP:
				$transport = Swift_SmtpTransport::newInstance();
				foreach ($this->smtpOptions as $option => $value) {
					$setter = 'set' . ucfirst($option);
					$transport->{$setter}($value); // sets option with the setter method
				}
				break;
			case self::TRANSPORT_PHP:
			default:
				$transport = Swift_MailTransport::newInstance();
				if (isset ($this->mailOptions)) {
					$transport->setExtraParams($this->mailOptions);
				}
				break;
		}
		return $transport;
	}

	/**
	 * Returns the mailer instance.
	 * @return Swift_Mailer the instance.
	 */
	public function getMailer() {
		if (isset($this->_mailer)) {
			return $this->_mailer;
		} else {
			$transport = $this->createTransport();
			return $this->_mailer = Swift_Mailer::newInstance($transport);
		}
	}

	/**
	 * Returns a list of the failed recipients for the most recent mail.
	 * @return array the recipients.
	 */
	public function getFailedRecipients() {
		return $this->_failedRecipients;
	}
}
