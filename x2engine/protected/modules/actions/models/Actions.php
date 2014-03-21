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

Yii::import('application.models.X2Model');

/**
 * This is the model class for table "x2_actions".
 * @package application.modules.actions.models
 */
class Actions extends X2Model {

    public $verifyCode; // CAPTCHA for guests using the publisher
    public $actionDescriptionTemp = ""; // Easy way to get around action text records

    /**
     * Returns the static model of the specified AR class.
     * @return Actions the static model class
     */
    public static function model($className = __CLASS__){
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName(){
        return 'x2_actions';
    }

    public function behaviors(){
        return array(
            'X2LinkableBehavior' => array(
                'class' => 'X2LinkableBehavior',
                'module' => 'actions'
            ),
            'X2TimestampBehavior' => array('class' => 'X2TimestampBehavior'),
            'tags' => array('class' => 'TagBehavior'),
            'ERememberFiltersBehavior' => array(
                'class' => 'application.components.ERememberFiltersBehavior',
                'defaults' => array(),
                'defaultStickOnClear' => false
            ),
            'permissions' => array('class' => 'X2PermissionsBehavior'),
        );
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules(){
        return array(
            array('allDay', 'boolean'),
            array('associationId,associationType','requiredAssoc'),
            array('createDate, completeDate, lastUpdated', 'numerical', 'integerOnly' => true),
            array('id,assignedTo,actionDescription,visibility,associationId,associationType,'.
                'associationName,dueDate,priority,type,createDate,complete,reminder,completedBy,'.
                'completeDate,lastUpdated,updatedBy,color', 'safe'),
            array('verifyCode', 'captcha', 'allowEmpty' => !CCaptcha::checkRequirements(), 'on' => 'guestCreate'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations(){
        return array_merge(parent::relations(), array(
            'workflow' => array(self::BELONGS_TO, 'Workflow', 'workflowId'),
            'actionText' => array(self::HAS_ONE, 'ActionText', 'actionId'),
            'timers' => array(self::HAS_MANY,'ActionTimer','actionId'),
            'assignee' => array(self::BELONGS_TO,'User',array('assignedTo'=>'username')),
        ));
    }

    /**
     * Returns action type specific attribute labels
     * @return String
     */
    public function getAttributeLabel ($attribute, $short=false) {
        $label = '';
        
        if ($attribute === 'dueDate') {
            switch ($this->type) {
                case 'time':
                case 'call':
                    if ($short) 
                        $label = Yii::t('actions', 'Start');
                    else
                        $label = Yii::t('actions', 'Time Started');
                    break;
                case 'event':
                    if ($short) 
                        $label = Yii::t('actions', 'Start');
                    else
                        $label = Yii::t('actions', 'Start Date');
                    break;
                default:
                    $label = parent::getAttributeLabel ($attribute);
            }
        } else if ($attribute === 'completeDate') {
            switch ($this->type) {
                case 'time':
                case 'call':
                    if ($short)
                        $label = Yii::t('actions', 'End');
                    else
                        $label = Yii::t('actions', 'Time Ended');
                    break;
                case 'event':
                    if ($short)
                        $label = Yii::t('actions', 'End');
                    else 
                        $label = Yii::t('actions', 'End Date');
                    break;
                default:
                    $label = parent::getAttributeLabel ($attribute);
            }
        } else if ($attribute === 'actionDescription') {
            $label = Yii::t('actions', 'Action Description');
        } else {
            $label = parent::getAttributeLabel ($attribute);
        }

        return $label;
    }


    public function getAttribute($name, $renderFlag = false){
        if ($name === 'actionDescription') {
            $model = ActionText::model ()->findByAttributes (
                array (
                    'actionId' => $this->id
                ));
            if ($model) return $model->text;
        } else {
            return parent::getAttribute ($name, $renderFlag);
        }
        return null;
    }


    /**
     * Fixes up record association, parses dates (since this doesn't use {@link X2Model::setX2Fields()})
     * @return boolean whether or not to save
     */
    public function beforeSave(){
        if($this->scenario !== 'workflow'){
            $association = self::getAssociationModel($this->associationType, $this->associationId);

            if($association === null){
                $this->associationName = 'None';
                $this->associationId = 0;
            }else{
                if($association->hasAttribute('name'))
                    $this->associationName = $association->name;
                if($association->asa('X2TimestampBehavior') !== null)
                    $association->updateLastActivity();
            }

            if($this->associationName == 'None' && $this->associationType != 'none')
                $this->associationName = ucfirst($this->associationType);

            $this->dueDate = Formatter::parseDateTime($this->dueDate);
            $this->completeDate = Formatter::parseDateTime($this->completeDate);
        }
        // Whether this is a "timed" action record:
        $timed = $this->isTimedType;
        
        if(empty($timeSpent) && !empty($this->completeDate) && !empty($this->dueDate) && $timed) {
            $this->timeSpent = $this->completeDate - $this->dueDate;
        }
        
        return parent::beforeSave();
    }

    public function beforeDelete() {
        
        return parent::beforeDelete();
    }

    public function afterSave(){
        // No action text exists for this yet
        if(!($this->actionText instanceof ActionText)){
            $actionText = new ActionText; // Create new oen
            $actionText->actionId = $this->id;
            $actionText->text = $this->actionDescriptionTemp; // A magic setter sets actionDescriptionTemp value
            $actionText->save();
        }else{ // We have an action text
            if($this->actionText->text != $this->actionDescriptionTemp){ // Only update if different
                $this->actionText->text = $this->actionDescriptionTemp;
                $this->actionText->save();
            }
        }
        return parent::afterSave();
    }

    public function requiredAssoc($attribute, $params = array()){
        if(!empty($this->type) && $this->type != 'event'){
            if(empty($this->$attribute) || strtolower($this->$attribute) == 'none')
                $this->addError($attribute, Yii::t('actions', 'Association is required for actions of this type.'));
        }
        return !$this->hasErrors();
    }

    public function afterFind(){
        if($this->actionText instanceof ActionText){
            $this->actionDescriptionTemp = $this->actionText->text;
        }
    }

    /**
     * Creates an action reminder event.
     * Fires the onAfterCreate event in {@link X2Model::afterCreate}
     */
    public function afterCreate(){
        if(empty($this->type)){
            $event = new Events;
            $event->timestamp = $this->createDate;
            $event->visibility = $this->visibility;
            $event->type = 'record_create';
            $event->associationType = 'Actions';
            $event->associationId = $this->id;
            $event->user = $this->assignedTo;
            $event->save();
        }
        if(empty($this->type) && $this->complete !== 'Yes' && ($this->reminder == 1 || $this->reminder == 'Yes')){
            $event = new Events;
            $event->timestamp = $this->dueDate;
            $event->visibility = $this->visibility;
            $event->type = 'action_reminder';
            $event->associationType = 'Actions';
            $event->associationId = $this->id;
            $event->user = $this->assignedTo;
            $event->save();
        }
        if($this->scenario != 'noNotif' && (Yii::app()->params->noSession || $this->assignedTo != Yii::app()->user->getName())){
            $notif = new Notification;
            $notif->user = $this->assignedTo;
            $notif->createdBy = (Yii::app()->params->noSession) ? 'API' : Yii::app()->user->getName();
            $notif->createDate = time();
            $notif->type = 'create';
            $notif->modelType = 'Actions';
            $notif->modelId = $this->id;
            $notif->save();
        }
        if(Yii::app()->params->noSession && !$this->asa('changelog')){
            X2Flow::trigger('RecordCreateTrigger', array('model' => $this));
        }
        parent::afterCreate();
    }

    /**
     * Deletes the action reminder event, if any
     * Fires the onAfterDelete event in {@link X2Model::afterDelete}
     */
    public function afterDelete(){
        X2Model::model('Events')->deleteAllByAttributes(array('associationType' => 'Actions', 'associationId' => $this->id, 'type' => 'action_reminder'));
        X2Model::model('ActionText')->deleteByPk($this->id);
         
        parent::afterDelete();
    }

    public function setActionDescription($value){
        // Magic setter stores value in actionDescriptionTemp until saved
        $this->actionDescriptionTemp = $value;
    }

    public function getActionDescription(){
        // Magic getter only ever refers to actionDescriptionTemp
        return $this->actionDescriptionTemp;
    }

    /**
     * return an array of possible colors for an action
     */
    public static function getColors(){
        return array(
            'Green' => Yii::t('actions', 'Green'),
            '#3366CC' => Yii::t('actions', 'Blue'),
            'Red' => Yii::t('actions', 'Red'),
            'Orange' => Yii::t('actions', 'Orange'),
            'Black' => Yii::t('actions', 'Black'),
        );
    }

    /**
     * Marks the action complete and updates the record.
     * @param string $completedBy the user completing the action (defaults to currently logged in user)
     * @return boolean whether or not the action updated successfully
     */
    public function complete($completedBy = null, $notes = null){
        if($completedBy === null){
            $completedBy = Yii::app()->user->getName();
        }
        if(!is_null($notes)){
            $this->actionDescription.="\n\n".$notes;
        }

        $this->complete = 'Yes';
        $this->completedBy = $completedBy;
        $this->completeDate = time();

        $this->disableBehavior('changelog');

        if($result = $this->update()){

            X2Flow::trigger('ActionCompleteTrigger', array(
                'model' => $this,
                'user' => $completedBy
            ));

            // delete the action reminder event
            X2Model::model('Events')->deleteAllByAttributes(array('associationType' => 'Actions', 'associationId' => $this->id, 'type' => 'action_reminder'), 'timestamp > NOW()');

            $event = new Events;
            $event->type = 'action_complete';
            $event->visibility = $this->visibility;
            $event->associationType = 'Actions';
            $event->user = Yii::app()->user->getName();
            $event->associationId = $this->id;

            // notify the admin
            if($event->save() && !Yii::app()->user->checkAccess('ActionsAdminAccess')){
                $notif = new Notification;
                $notif->type = 'action_complete';
                $notif->modelType = 'Actions';
                $notif->modelId = $this->id;
                $notif->user = 'admin';
                $notif->createdBy = $completedBy;
                $notif->createDate = time();
                $notif->save();
            }
        }
        $this->enableBehavior('changelog');

        return $result;
    }

    /**
     * Marks the action incomplete and updates the record.
     * @return boolean whether or not the action updated successfully
     */
    public function uncomplete(){
        $this->complete = 'No';
        $this->completedBy = null;
        $this->completeDate = null;

        $this->disableBehavior('changelog');

        if($result = $this->update()){
            X2Flow::trigger('ActionUncompleteTrigger', array(
                'model' => $this,
                'user' => Yii::app()->user->getName()
            ));
        }
        $this->enableBehavior('changelog');

        return $result;
    }

    public function getName(){
        if(!empty($this->subject)){
            return $this->subject;
        }else{
            if($this->type == 'email'){
                return Formatter::parseEmail($this->actionDescription);
            }else{
                return Formatter::truncateText($this->actionDescription, 40);
            }
        }
    }

    public function getLink($length = 30, $frame = true){

        $text = $this->name;
        if($length && mb_strlen($text, 'UTF-8') > $length)
            $text = CHtml::encode(trim(mb_substr($text, 0, $length, 'UTF-8')).'...');
        if($frame){
            return CHtml::link($text, '#', array('class' => 'action-frame-link', 'data-action-id' => $this->id));
        }else{
            return CHtml::link($text, $this->getUrl());
        }
    }

    public function getAssociationLink(){
        $model = self::getAssociationModel($this->associationType, $this->associationId);
        if($model !== null)
            return $model->getLink();
        return false;
    }

    public function getRelevantTimestamp() {
        switch($this->type) {
            case 'attachment':
                $timestamp = $this->completeDate;
                break;
            case 'email': 
            case 'emailFrom': 
            case 'email_quote': 
            case 'email_invoice': 
                $timestamp = $this->completeDate; 
                break;
            case 'emailOpened': 
            case 'emailOpened_quote': 
            case 'email_opened_invoice': 
                $timestamp = $this->completeDate; 
                break;
            case 'event': 
                $timestamp = $this->completeDate; 
                break;
            case 'note': 
                $timestamp = $this->completeDate; 
                break;
            case 'quotes': 
                $timestamp = $this->createDate; 
                break;
            case 'time': 
                $timestamp = $this->createDate; 
                break;
            case 'webactivity': 
                $timestamp = $this->completeDate; 
                break;
            case 'workflow': 
                $timestamp = $this->completeDate; 
                break;
            default:
                $timestamp = $this->createDate;
        }
        return $timestamp;
    }

    public static function parseStatus($dueDate){
        if(empty($dueDate)) // there is no due date
            return false;
        if(!is_numeric($dueDate))
            $dueDate = strtotime($dueDate); // make sure $date is a proper timestamp

        $timeLeft = $dueDate - time(); // calculate how long till due date
        if($timeLeft < 0) {
            return 
                "<span class='overdue'>".
                    Formatter::formatDueDate($dueDate).
                "</span>"; // overdue by X hours/etc
        } else {
            return Formatter::formatDueDate($dueDate);
        }
    }

    public function formatDueDate () {
        if (in_array ($this->type, array ('call', 'time', 'event'))) {
            return Formatter::formatDueDate($this->dueDate);
        } else {
            return self::parseStatus ($this->dueDate);
        }
    }

    public static function formatTimeLength($seconds){
        $seconds = abs($seconds);
        if($seconds < 60)
            return Yii::t('app', '{n} second|{n} seconds', $seconds); // less than 1 min
        if($seconds < 3600)
            return Yii::t('app', '{n} minute|{n} minutes', floor($seconds / 60)); // minutes (less than an hour)
        if($seconds < 86400)
            return Yii::t('app', '{n} hour|{n} hours', floor($seconds / 3600)); // hours (less than a day)
        if($seconds < 5184000)
            return Yii::t('app', '{n} day|{n} days', floor($seconds / 86400)); // days (less than 60 days)
        else
            return Yii::t('app', '{n} month|{n} months', floor($seconds / 2592000)); // months (more than 90 days)
    }

    // finds record for the "owner" of a action, using the owner type and ID
    public static function getOwnerModel($ownerType, $ownerId){
        if(!(empty($ownerType) || empty($ownerId)) && X2Model::getModelName($ownerType)){ // both ID and type must be set
            return X2Model::model(X2Model::getModelName($ownerType))->findByPk($ownerId);

            // if($ownerType=='projects')
            // return X2Model::model('ProjectChild')->findByPk($ownerId);
            // if($ownerType=='contacts')
            // return X2Model::model('Contacts')->findByPk($ownerId);
            // if($ownerType=='accounts')
            // return X2Model::model('Accounts')->findByPk($ownerId);
            // if($ownerType=='cases')
            // return X2Model::model('CaseChild')->findByPk($ownerId);
            // if($ownerType=='opportunities')
            // return X2Model::model('Opportunity')->findByPk($ownerId);
        }
        return null; // either the type is unkown, or there simply is no owner
    }

    // creates virtual attribute for owner's name, if exists
    public function getOwnerName(){
        $ownerModel = Actions::getOwnerModel($this->ownerType, $this->ownerId);
        if($ownerModel !== null)
            return $ownerModel->name; // get name of owner
        else
            return false;
    }

    public static function createCondition($filters){
        Yii::app()->params->profile->actionFilters = json_encode($filters);
        Yii::app()->params->profile->update(array('actionFilters'));
        $criteria = X2Model::model('Actions')->getAccessCriteria();
        $criteria->addCondition("(type !='workflow' AND type!='email' AND type!='event' AND type!='emailFrom' AND type!='attachment' AND type!='webactivity' AND type!='quotes' AND type!='emailOpened' AND type!='note') OR type IS NULL");
        if(isset($filters['complete'], $filters['assignedTo'], $filters['dateType'], $filters['dateRange'], $filters['order'], $filters['orderType'])){
            switch($filters['complete']){
                case "No":
                    $criteria->addCondition("complete='No' OR complete IS NULL");
                    break;
                case "Yes":
                    $criteria->addCondition("complete='Yes'");
                    break;
                case 'all':
                    break;
            }
            switch($filters['assignedTo']){
                case 'me':
                    $criteria->addCondition("assignedTo='".Yii::app()->user->getName()."'");
                    break;
                case 'both':
                    $criteria->addCondition("assignedTo='".Yii::app()->user->getName()."' OR assignedTo='Anyone' OR assignedTo=''");
                    break;
            }
            switch($filters['dateType']){
                case 'due':
                    $dateField = 'dueDate';
                    break;
                case 'create':
                    $dateField = 'createDate';
            }
            switch($filters['dateRange']){
                case 'today':
                    if($dateField == 'dueDate'){
                        $criteria->addCondition("IFNULL(dueDate, createDate) <= ".strtotime('today 11:59 PM'));
                    }else{
                        $criteria->addCondition("$dateField >= ".strtotime('today')." AND $dateField <= ".strtotime('today 11:59 PM'));
                    }
                    break;
                case 'tomorrow':
                    if($dateField == 'dueDate'){
                        $criteria->addCondition("IFNULL(dueDate, createDate) <= ".strtotime("tomorrow 11:59 PM"));
                    }else{
                        $criteria->addCondition("$dateField >= ".strtotime('tomorrow')." AND $dateField <= ".strtotime("tomorrow 11:59 PM"));
                    }
                    break;
                case 'week':
                    if($dateField == 'dueDate'){
                        $criteria->addCondition("IFNULL(dueDate, createDate) <= ".strtotime("Sunday 11:59 PM"));
                    }else{
                        $criteria->addCondition("$dateField >= ".strtotime('Monday')." AND $dateField <= ".strtotime("Sunday 11:59 PM"));
                    }
                    break;
                case 'month':
                    if($dateField == 'dueDate'){
                        $criteria->addCondition("IFNULL(dueDate, createDate) <= ".strtotime("last day of this month 11:59 PM"));
                    }else{
                        $criteria->addCondition("$dateField >= ".strtotime('first day of this month')." AND $dateField <= ".strtotime("last day of this month 11:59 PM"));
                    }
                    break;
                case 'range':
                    if(!empty($filters['start']) && !empty($filters['end'])){
                        if($dateField == 'dueDate'){
                            $criteria->addCondition("IFNULL(dueDate, createDate) >= ".strtotime($filters['start'])." AND IFNULL(dueDate, createDate) <= ".strtotime($filters['end'].' 11:59 PM'));
                        }else{
                            $criteria->addCondition("$dateField >= ".strtotime($filters['start'])." AND $dateField <= ".strtotime($filters['end']));
                        }
                    }
                    break;
            }
            switch($filters['order']){
                case 'due':
                    $orderField = "IFNULL(dueDate, createDate)";
                    break;
                case 'create':
                    $orderField = 'createDate';
                    break;
                case 'priority':
                    $orderField = 'priority';
                    break;
            }
            switch($filters['orderType']){
                case 'desc':
                    $criteria->order = "$orderField DESC";
                    break;
                case 'asc':
                    $criteria->order = "$orderField ASC";
                    break;
            }
        }
        return $criteria;
    }

    public function search($criteria = null){
        if(!$criteria instanceof CDbCriteria){
            $criteria = $this->getAccessCriteria();
            $criteria->addCondition(
                '(type != "workflow" AND type!="email" AND type!="event" '.
                'AND type!="emailFrom") OR type IS NULL');
            $criteria->addCondition(
                "assignedTo='".Yii::app()->user->getName()."' AND complete!='Yes' AND ".
                "IFNULL(dueDate, createDate) <= '".strtotime('today 11:59 PM')."'");
        }
        return $this->searchBase($criteria);
    }

    public function searchIndex(){
        $criteria = new CDbCriteria;
        $parameters = array(
            'condition' => 
                "(assignedTo='Anyone' OR assignedTo='".Yii::app()->user->getName().
                "' OR assignedTo='' OR assignedTo IN (
                    SELECT groupId 
                    FROM x2_group_to_user 
                    WHERE userId='".Yii::app()->user->getId()."')
                ) AND dueDate <= '".mktime(23, 59, 59)."' AND 
                    ((type != \"workflow\" AND type!=\"email\" AND type!=\"event\" AND 
                    type!=\"emailFrom\") OR type IS NULL)", 
                'limit' => ceil(ProfileChild::getResultsPerPage() / 2));
        $criteria->scopes = array('findAll' => array($parameters));
        return $this->searchBase($criteria);
    }

    public function searchComplete(){
        $criteria = new CDbCriteria;
        if(!Yii::app()->user->checkAccess('ActionsAdmin')){
            $parameters = array(
                "condition" => 
                    "completedBy='".Yii::app()->user->getName()."' AND complete='Yes'", 
                "limit" => ceil(ProfileChild::getResultsPerPage() / 2));
            $criteria->scopes = array('findAll' => array($parameters));
        }
        return $this->searchBase($criteria);
    }

    public function searchAll(){
        $criteria = new CDbCriteria;
        $parameters = array(
            "condition" => 
                "(assignedTo='".Yii::app()->user->getName()."' OR assignedTo IN (
                    SELECT groupId 
                    FROM x2_group_to_user 
                    WHERE userId='".Yii::app()->user->getId()."'))", 
            'limit' => ceil(ProfileChild::getResultsPerPage() / 2));
        $criteria->scopes = array('findAll' => array($parameters));
        return $this->searchBase($criteria);
    }

    public function searchGroup(){
        $criteria = new CDbCriteria;
        if(!Yii::app()->user->checkAccess('ActionsAdmin')){
            $parameters = array(
                "condition" => 
                    "(visibility='1' OR assignedTo='".Yii::app()->user->getName()."' OR 
                    assignedTo IN (
                        SELECT groupId 
                        FROM x2_group_to_user 
                        WHERE userId='".Yii::app()->user->getId()."')) AND complete!='Yes'", 
                'limit' => ceil(ProfileChild::getResultsPerPage() / 2));
            $criteria->scopes = array('findAll' => array($parameters));
        }
        return $this->searchBase($criteria);
    }

    public function searchAllGroup(){
        $criteria = new CDbCriteria;
        if(!Yii::app()->user->checkAccess('ActionsAdmin')){
            $parameters = array(
                "condition" => 
                    "(visibility='1' OR assignedTo='".Yii::app()->user->getName()."' OR 
                      assignedTo IN (
                        SELECT groupId 
                        FROM x2_group_to_user 
                        WHERE userId='".Yii::app()->user->getId()."'))", 
                'limit' => ceil(ProfileChild::getResultsPerPage() / 2));
            $criteria->scopes = array('findAll' => array($parameters));
        }
        return $this->searchBase($criteria);
    }

    public function searchAllComplete(){
        $criteria = new CDbCriteria;
        if(!Yii::app()->user->checkAccess('ActionsAdmin')){
            $parameters = array(
                "condition" => 
                    "(visibility='1' OR assignedTo='".Yii::app()->user->getName()."' OR 
                      assignedTo IN (
                        SELECT groupId 
                        FROM x2_group_to_user 
                        WHERE userId='".Yii::app()->user->getId()."')) AND complete='Yes'", 
                'limit' => ceil(ProfileChild::getResultsPerPage() / 2));
            $criteria->scopes = array('findAll' => array($parameters));
        }
        return $this->searchBase($criteria);
    }

    public function searchAdmin(){
        $criteria = new CDbCriteria;

        return $this->searchBase($criteria);
    }

    public function searchBase($criteria, $pageSize=null, $uniqueId=null){

        $this->compareAttributes($criteria);
        /*$criteria->with = 'actionText';
        $criteria->compare('actionText.text', $this->actionDescriptionTemp, true);*/
        if(!empty($criteria->order)){
            $criteria->order = $order = "sticky DESC, ".$criteria->order;
        }else{
            $order = 
                'sticky DESC, IF(
                    complete="No", IFNULL(dueDate, IFNULL(createDate,0)), 
                    GREATEST(createDate, IFNULL(completeDate,0), IFNULL(lastUpdated,0))) DESC';
        }
        $dataProvider = new SmartDataProvider('Actions', 
            array(
                'sort' => array(
                    'defaultOrder' => $order,
                ),
                'pagination' => array(
                    'pageSize' => ProfileChild::getResultsPerPage()
                ),
                'criteria' => $criteria,
            ), $uniqueId);
        return $dataProvider;
    }

    /**
     * Override parent method to exclude actionDescription
     */
    public function compareAttributes(&$criteria){
        foreach(self::$_fields[$this->tableName()] as &$field){
            if($field->fieldName != 'actionDescription'){
                $this->compareAttribute ($criteria, $field);
            }
        }
    }

    public function syncGoogleCalendar($operation){
        $profiles = array();

        if(!is_numeric($this->assignedTo)){ // assigned to user
            $profiles[] = X2Model::model('Profile')->findByAttributes(array('username' => $this->assignedTo));
        }else{ // Assigned to group
            $groups = Yii::app()->db->createCommand()
                    ->select('userId')
                    ->from('x2_group_to_user')
                    ->where('groupId=:assignedTo', array(':assignedTo' => $this->assignedTo))
                    ->queryAll();
            foreach($groups as $group)
                $profile[] = X2Model::model('Profile')->findByPk($group['userId']);
        }

        foreach($profiles as &$profile){
            if($profile !== null){
                if($operation === 'create')
                    $profile->syncActionToGoogleCalendar($this); // create action to Google Calendar
                elseif($operation === 'update')
                    $profile->updateGoogleCalendarEvent($this); // update action to Google Calendar
                elseif($operation === 'delete')
                    $profile->deleteGoogleCalendarEvent($this); // delete action in Google Calendar
            }
        }
    }

    /**
     * Returns a link which opens an action view dialog. Event bound in actionFrames.js. 
     * @param string $linkText The text to display in the <a> tag.
     */
    public function getActionLink ($linkText) {
        return CHtml::link(
            $linkText,
            '#',
            array(
                'class' => 'action-frame-link',
                'data-action-id' => $this->id
            )
        );
    }
     
    /**
     * Completes/uncompletes set of actions 
     * @param string $operation <'complete' | 'uncomplete'>
     * @param array $ids
     * @return int $updated number of actions updated successfully
     */
    public static function changeCompleteState ($operation, $ids) {
        $updated = 0;
        foreach(self::model()->findAllByPk ($ids) as $action){
            if($action === null)
                continue;

            $inGroup = false;

            // we have an action assigned to a group? Then check if we are in the group
            if(ctype_digit((string) $action->assignedTo)) 
                $inGroup = Groups::inGroup(Yii::app()->user->id, $action->assignedTo);

            if(Yii::app()->user->getName() == $action->assignedTo || 
               $action->assignedTo == 'Anyone' || $action->assignedTo == '' || $inGroup || 
               Yii::app()->params->isAdmin){ // make sure current user can edit this action

                if($operation === 'complete') {
                    if ($action->complete()) $updated++;
                } elseif($operation === 'uncomplete') {
                    if ($action->uncomplete()) $updated++;
                }
            }
        }
        return $updated;
    }

    /**
     * Returns whether this is the type of action that can be time-tracked
     */
    public function getIsTimedType() {
        return $this->type == 'time' || $this->type == 'call';
    }

      

    /**
     * Override parent method so that action type can be set from X2Flow create action 
     */
    public function getEditableFieldNames ($suppressAttributeLabels=true) {
        $editableFieldNames = parent::getEditableFieldNames ($suppressAttributeLabels);
        if ($this->scenario === 'X2FlowCreateAction') {
            if ($suppressAttributeLabels) {
                $editableFieldNames[] = 'type';
            } else {
                $editableFieldNames['type'] = $this->getAttributeLabel ('type');
            }
        }
        return $editableFieldNames;
    }

}
