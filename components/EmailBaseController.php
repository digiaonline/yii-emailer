<?php
/**
 * EmailBaseController class file.
 * @author Christoffer Niska <christoffer.niska@nordsoftware.com>
 * @copyright Copyright &copy; Nord Software 2013-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package vendor.nordsoftware.emailer.components
 */

/**
 * Base controller.
 */
class EmailBaseController extends Controller {
	/**
	 * @var array the breadcrumbs of the current page.
	 */
	public $breadcrumbs = array();
	/**
	 * @var array context menu items.
	 */
	public $menu = array();
}