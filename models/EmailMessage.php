<?php
/**
 * EmailMessage class file.
 * @author Christoffer Niska <christoffer.niska@nordsoftware.com>
 * @copyright Copyright &copy; Nord Software 2013-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package vendor.nordsoftware.emailer.models
 */

Yii::import('vendor.nordsoftware.yii-utils.NSActiveRecord');

// Load the Swift Mailer autoloader.
require_once(__DIR__ . '/../../../swiftmailer/swiftmailer/lib/swift_required.php');
Yii::registerAutoloader(array('Swift', 'autoload'));
require_once(__DIR__ . '/../../../swiftmailer/swiftmailer/lib/swift_init.php');

/**
 * This is the model class for table "email_message".
 *
 * The followings are the available columns in table 'email':
 * @property string $id
 * @property string $from
 * @property string $to
 * @property string $cc
 * @property string $bcc
 * @property string $subject
 * @property string $body
 * @property string $headers
 * @property string $contentType
 * @property string $charset
 * @property string $created
 * @property string $sentTime
 * @property integer $status
 */
class EmailMessage extends NSActiveRecord {
	/**
	 * @var Swift_Message the swift message.
	 */
	private $_message;

	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return EmailMessage the static model class
	 */
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName() {
		return 'email_message';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules() {
		return array(
			array('from, to, subject, body, headers, created, contentType, charset', 'required'),
			array('status', 'numerical', 'integerOnly' => true),
			array('subject', 'length', 'max' => 255),
			array('cc, bcc, sentTime', 'safe'),
			// The following rule is used by search().
			array('id, from, to, cc, bcc, subject, body, headers, created, status', 'safe', 'on' => 'search'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels() {
		return array(
			'id' => Yii::t('label', 'ID'),
			'from' => Yii::t('label', 'From'),
			'to' => Yii::t('label', 'To'),
			'cc' => Yii::t('label', 'Cc'),
			'bcc' => Yii::t('label', 'Bcc'),
			'subject' => Yii::t('label', 'Subject'),
			'body' => Yii::t('label', 'Body'),
			'headers' => Yii::t('label', 'Headers'),
			'contentType' => Yii::t('label', 'Content Type'),
			'charset' => Yii::t('label', 'Charset'),
			'created' => Yii::t('label', 'Create Time'),
			'sentTime' => Yii::t('label', 'Sent Time'),
			'status' => Yii::t('label', 'Status'),
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search() {
		$criteria = new CDbCriteria;

		$criteria->compare('id', $this->id);
		$criteria->compare('from', $this->from, true);
		$criteria->compare('to', $this->to, true);
		$criteria->compare('cc', $this->cc, true);
		$criteria->compare('bcc', $this->bcc, true);
		$criteria->compare('subject', $this->subject, true);
		$criteria->compare('body', $this->body, true);
		$criteria->compare('headers', $this->headers, true);
		$criteria->compare('created', $this->created, true);
		$criteria->compare('sentTime', $this->sentTime, true);
		$criteria->compare('status', $this->status);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}

	/**
	 * Returns the number of recipients for this message.
	 * @return integer the count.
	 */
	public function getRecipientCount() {
		$message = $this->getMessage();
		return $message instanceof Swift_Message ? count($message->getTo()) : -1;
	}

	/**
	 * Creates a Swift_Message instance for this model.
	 * @return Swift_Message the message.
	 */
	public function createMessage() {
		/** @var Swift_Message $message */
		$message = Swift_Message::newInstance();
		$from = $this->from;
		$to = $this->to;
		$subject = $this->subject;
		$body = $this->body;
		$contentType = $this->contentType;
		$charset = $this->charset;

		// Set variables to message
		$message->setFrom($from)
			->setTo($to)
			->setSubject($subject)
			->setBody($body, $contentType, $charset);

		return $message;
	}

	/**
	 * Returns the swift message instance.
	 * @return Swift_Message the instance.
	 */
	public function getMessage() {
		if (isset($this->_message)) {
			return $this->_message;
		} else {
			return $this->_message = $this->createMessage();
		}
	}

	/**
	 * Converts this model to a string.
	 * @return string the text.
	 */
	public function __toString() {
		$message = $this->getMessage();
		$from = implode(', ', array_keys($message->getFrom()));
		$to = implode(', ', array_keys($message->getTo()));
		$headers = implode('', $message->getHeaders()->getAll());
		$body = $message->getBody();
		return 'From: ' . $from . PHP_EOL
			. 'To: ' . $to . PHP_EOL
			. 'Headers: ' . PHP_EOL . $headers
			. 'Body: ' . PHP_EOL . $body;
	}
}
