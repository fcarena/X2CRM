<?php
/*****************************************************************************************
 * X2Engine Open Source Edition is a customer relationship management program developed by
 * X2Engine, Inc. Copyright (C) 2011-2014 X2Engine Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY X2ENGINE, X2ENGINE DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 * 
 * You can contact X2Engine, Inc. P.O. Box 66752, Scotts Valley,
 * California 95067, USA. or at email address contact@x2engine.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * X2Engine" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by X2Engine".
 *****************************************************************************************/

/**
 * Base widget class for all of X2Engine's widgets
 *
 * @property X2WebModule $module
 * @package application.components
 */
abstract class X2Widget extends CWidget {

    protected $_module;

    protected $_packages;

    /**
     * @var string $JSClass
     */
    public $JSClass = 'Widget'; 

    /**
     * @var string $element
     */
    public $element; 

    /**
     * @var string $namespace
     */
    public $namespace = ''; 

	/**
	 * Constructor.
	 * @param CBaseController $owner owner/creator of this widget. It could be either a widget or a controller.
	 */
	public function __construct($owner=null)
	{
        parent::__construct ($owner);
        $this->attachBehaviors($this->behaviors());
	}

    public function behaviors () {
        return array ();
    }

    protected $_JSClassParams;
    public function getJSClassParams () {
        if (!isset ($this->_JSClassParams)) {
            $this->_JSClassParams = array (
                'element' => $this->element,
                'namespace' => $this->namespace,
            );
        }
        return $this->_JSClassParams;
    }

    public function instantiateJSClass () {
        Yii::app()->clientScript->registerScript ($this->namespace.'JSClassInstantiation', "
            $(function () {
                x2.".$this->namespace.lcfirst ($this->JSClass)." = 
                    new x2.$this->JSClass (".
                        CJSON::encode ($this->getJSClassParams ()).
                    ");
            });
        ", CClientScript::POS_END);
    }

    public function registerPackages () {
        Yii::app()->clientScript->registerPackages ($this->getPackages (), true);
    }

    public function getPackages () {
        if (!isset ($this->_packages)) {
            $this->_packages = array (
                'X2Widget' => array(
                    'baseUrl' => Yii::app()->request->baseUrl,
                    'js' => array(
                        'js/X2Widget.js',
                    ),
                ),
            );
        }
        return $this->_packages;
    }

	/**
	 * Renders a view file.
	 * Overrides {@link CBaseController::renderFile} to check if the requested view 
	 * has a version in /custom, and uses that if it exists.
	 *
	 * @param string $viewFile view file path
	 * @param array $data data to be extracted and made available to the view
	 * @param boolean $return whether the rendering result should be returned instead of being 
     *  echoed
	 * @return string the rendering result. Null if the rendering result is not required.
	 * @throws CException if the view file does not exist
	 */
	public function renderFile($viewFile,$data=null,$return=false) {
		$viewFile = Yii::getCustomPath($viewFile);
		return parent::renderFile($viewFile,$data,$return);
	}

    /**
     * Runs an arbitrary function inside a partial view. All scripts registered get processed.
     * Allows scripts associated with a widget to be returned in AJAX response.
     * 
     * @param function $function
     */
    public static function ajaxRender ($function) {
        Yii::app()->controller->renderPartial (
            'application.components.views._ajaxWidgetContents',
            array (
                'run' => $function
            ), false, true);
    }

    /**
     * Getter for {@link module}.
     *
     * Can automatically recognize when a component is a member of a module's
     * collection of components.
     * @return type
     */
    public function getModule(){
        if(!isset($this->_module)){
            // Ascertain the module to which the widget belongs by virtue of its
            // location in the file system:
            $rc = new ReflectionClass(get_class($this));
            $path = $rc->getFileName();
            $ds = preg_quote(DIRECTORY_SEPARATOR,'/');
            $pathPattern = array(
                'protected',
                'modules',
                '(?P<module>[a-z0-9]+)',
                'components',
                '\w+\.php'
            );
            if(preg_match('/'.implode($ds,$pathPattern).'$/',$path,$match)) {
                // The widget is part of a module:
                $this->_module = Yii::app()->getModule($match['module']);
            } else {
                // Assume the widget's module is the currently-requested module:
                $this->_module = Yii::app()->controller->module;
            }
        }
        return $this->_module;
    }

    public function setModule ($moduleName) {
        $this->_module = Yii::app()->getModule($moduleName);
    }

}
?>
