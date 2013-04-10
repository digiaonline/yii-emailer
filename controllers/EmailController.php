<?php
/**
 * EmailController class file.
 * @author Christoffer Niska <christoffer.niska@nordsoftware.com>
 * @copyright Copyright &copy; Nord Software 2013-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package vendor.nordsoftware.emailer.controllers
 */

/**
 * Email controller.
 */
class EmailController extends CController {
	/**
	 * @var string the name of the default action.
	 */
	public $defaultAction = 'view';

	/**
	 * Displays an email.
	 * @param integer $id the model id.
	 */
	public function actionView($id) {
		$model = $this->loadModel($id);
		echo $model->body;
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the model id.
	 * @throws CHttpException if the model is not found.
	 * @return EmailMessage the model.
	 */
	protected function loadModel($id) {
		$model = EmailMessage::model()->findByPk($id);
		if ($model === null) {
			throw new CHttpException(404, t('email', 'Page not found.'));
		}
		return $model;
	}
}