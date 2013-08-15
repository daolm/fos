<?php

class PollController extends Controller
{

    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'accessControl', // perform access control for CRUD operations
            'postOnly + delete', // we only allow deletion via POST request
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array(
                'allow',
                'actions' => array('create', 'index', 'view', 'list', 'update', 'vote',
                    'invite', 'addinvite', 'getInfo'),
                'users' => array('@'),
            ),
            array(
                'allow',
                'actions' => array('delete'),
                'expression' => '$user->is_admin'
            ),
            array(
                'deny',
                'users' => array('*'),
            ),
        );
    }

    /**
     * @author Nguyen Van Cuong
     * function view detail poll
     */
    public function actionView($id)
    {
//  set notification to be viewed when user access the poll page
        $criteria = new CDbCriteria();
        $criteria->addCondition(array(
            'poll_id=:id ', 'viewed=:viewed', 'receiver_id=:receiver_id'
        ));
        $criteria->params = array(
            ':id' => $id, ':viewed' => 0, ':receiver_id' => $this->current_user->id
        );
        Notification::model()->updateAll(array('viewed' => 1),$criteria);

        unset(Yii::app()->session['poll_creating']);
        $poll = $this->loadModel($id);
        $user = $poll->user;
        $choices = $poll->choices;
        $comments = $poll->loadComment();
        $users_invited = User::model()->invitedTo($id, $this->current_user->id, null)->findAll();
        $all_votes = $this->current_user->getAllVotes($poll->id);
        $can_views = $this->current_user->canViewPoll($poll);
        $can_votes = $this->current_user->canVotePoll($poll);
        $can_show_result = $this->current_user->canViewResult($poll);
        $can_show_voter = $this->current_user->canViewVoter($poll);
        $voting = $poll->isVoting();
        if ($can_views) {
            $this->render(
                'view',
                array(
                    'poll' => $poll,
                    'user' => $user,
                    'choices' => $choices,
                    'all_votes' => $all_votes,
                    'comments' => $comments,
                    'users_invited' => $users_invited,
                    'can_views' => $can_views,
                    'can_votes' => $can_votes,
                    'can_show_result' => $can_show_result,
                    'can_show_voter' => $can_show_voter,
                    'voting' => $voting,
                )
            );
        } else {
            Yii::app()->user->setFlash('warning','Can not acess');
            $this->redirect(array('index'));
        }
    }

    /**
     * @author Nguyen Van Cuong
     */
    public function loadModel($id) //Find Poll where id = $id
    {
        $model = Poll::model()->findByPk($id);
        if ($model === null)
            throw new CHttpException(404, 'The requested page does not exist.');
        return $model;
    }

    /**
     * @author Nguyen Van Cuong
     */
    public function actionDelete($id) //delete Poll where id = $id
    {
        $poll = $this->loadModel($id);
        $poll->delete();
        $this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('index'));
    }

    /**
     * Creates a new model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @author Nguyen Thi Huyen
     */
    public function actionCreate()
    {
        Yii::app()->session['poll_creating'] = true;
        $poll = new Poll;
        if (isset($_POST['Poll'])) {
            $poll->attributes = $_POST['Poll'];
            $poll->description = CHtml::encode($poll->description);
            $poll->question = CHtml::encode($poll->question);
            $poll->user_id = Yii::app()->user->getId();
            if ($poll->poll_type == Poll::POLL_TYPE_SETTINGS_ANONYMOUS) {
                $poll->result_detail_type = Poll::RESULT_DETAIL_SETTINGS_ONLY_PERCENTAGE;
            }
            
            if (strtotime($poll->start_at) < time()) {
                $poll->start_at = date('Y-m-d H:i:s', time());
            }
            if ((strtotime($poll->end_at) - strtotime($poll->start_at)) < 10 * 60) {
                $poll->end_at = date('Y-m-d H:i:s', strtotime($poll->start_at) + 10 * 60);
            }
            
            if ($poll->save()) {
                Yii::app()->user->setFlash('success', 'You created successfully!');
                $this->redirect(array('choice/index', 'poll_id' => $poll->id));
            }
        }

        $this->render('create', array(
            'poll' => $poll,
        ));
    }

    /**
     *  @author Vu Dang Tung , Nguyen Van Cuong
     */
    public function actionIndex($status = null, $poll_type = null,
        $display_type = null, $result_display_type = null,
        $result_detail_type = null, $result_show_time_type = null)
    {
        $criteria = new CDbCriteria();

        if ($status != null && $status != 'is_multichoice') {
            $criteria->addCondition('is_multichoice='.$status);
        }
        if ($poll_type != null && $poll_type != 'poll_type') {
            $criteria->addCondition('poll_type='.$poll_type);
        }
        if ($display_type != null && $display_type != 'display_type') {
            $criteria->addCondition('display_type='.$display_type);
        }
        if ($result_display_type != null && $result_display_type != 'result_display_type') {
            $criteria->addCondition('result_display_type='.$result_display_type);
        }
        if ($result_detail_type != null && $result_detail_type != 'result_detail_type') {
            $criteria->addCondition('result_detail_type='.$result_detail_type);
        }
        if ($result_show_time_type != null && $result_show_time_type != 'result_show_time_type') {
            $criteria->addCondition('result_show_time_type='.$result_show_time_type);
        }
        if ( $this->current_user->is_admin) {
            $count = Poll::model()->count($criteria);
            $pages = new CPagination($count);
            $pages->pageSize = 15;
            $pages->applyLimit($criteria);
            $polls = Poll::model()->findAll($criteria);
        } else {
            $count = Poll::model()->canBeSeenBy($this->current_user->id)->count($criteria);
            $pages = new CPagination($count);
            $pages->pageSize = 15;
            $pages->applyLimit($criteria);
            $polls = Poll::model()->canBeSeenBy($this->current_user->id)->findAll($criteria);
        }
        $this->render('index', array(
            'polls' => $polls,
            'title' => 'All Polls',
            'status' => $status,
            'poll_type' => $poll_type,
            'display_type' => $display_type,
            'result_display_type' => $result_display_type,
            'result_show_time_type' => $result_show_time_type,
            'result_detail_type' => $result_detail_type,
            'pages' => $pages,
        ));
    }

    /*
     * @author Nguyen Van Cuong
     */
    public function actionList($user_id = null, $status = null, $poll_type = null,
        $display_type = null, $result_display_type = null,
        $result_detail_type = null, $result_show_time_type = null)
    {
        $criteria = new CDbCriteria();
        if ($status != null && $status != 'is_multichoice') {
            $criteria->addCondition('is_multichoice='.$status);
        }
        if ($poll_type != null && $poll_type != 'poll_type') {
            $criteria->addCondition('poll_type='.$poll_type);
        }
        if ($display_type != null && $display_type != 'display_type') {
            $criteria->addCondition('display_type='.$display_type);
        }
        if ($result_display_type != null && $result_display_type != 'result_display_type') {
            $criteria->addCondition('result_display_type='.$result_display_type);
        }
        if ($result_detail_type != null && $result_detail_type != 'result_detail_type') {
            $criteria->addCondition('result_detail_type='.$result_detail_type);
        }
        if ($result_show_time_type != null && $result_show_time_type != 'result_show_time_type') {
            $criteria->addCondition('result_show_time_type='.$result_show_time_type);
        }

        if ($user_id != null) {
            $criteria->addCondition('user_id='.$user_id);
            $count = Poll::model()->canBeSeenBy($this->current_user->id)->count($criteria);
            $pages = new CPagination($count);
            $pages->pageSize = 15;
            $pages->applyLimit($criteria);
            $polls = Poll::model()->canBeSeenBy($this->current_user->id)->findAll($criteria);
        } else {
            $criteria->addCondition('user_id='.$this->current_user->id);
            $count = Poll::model()->count($criteria);
            $pages = new CPagination($count);
            $pages->pageSize = 15;
            $pages->applyLimit($criteria);
            $polls = Poll::model()->findAll($criteria);
        }
        $this->render('index', array(
            'polls' => $polls,
            'title' => 'List Polls',
            'status' => $status,
            'poll_type' => $poll_type,
            'display_type' => $display_type,
            'result_display_type' => $result_display_type,
            'result_show_time_type' => $result_show_time_type,
            'result_detail_type' => $result_detail_type,
            'pages' => $pages,
            'user_id' => $user_id,
        ));
    }

    public function actionUpdate($id) // id of poll
    {
        $model = $this->loadModel($id);
        $voting = $model->isVoting();
        $voted = $model->isVoted();
        if (isset($_POST['Poll'])) {
            $model->logChangedAttributes($_POST['Poll']);
            $model->attributes = $_POST['Poll'];
            $poll->description = CHtml::encode($poll->description);
            $poll->question = CHtml::encode($poll->question);
            if ($model->poll_type == Poll::POLL_TYPE_SETTINGS_ANONYMOUS) {
                $model->result_detail_type = Poll::RESULT_DETAIL_SETTINGS_ONLY_PERCENTAGE;
            }

            if (!$voting && !$voted && strtotime($model->start_at) < time()) {
                $model->start_at = date('Y-m-d H:i:s', time());
            }
            
            if ($voting && strtotime($model->end_at) < time()) {
                $model->end_at = date('Y-m-d H:i:s', time());
            }
            
            if (strtotime($model->end_at) - strtotime($model->start_at) < 10 * 60) {
                $model->end_at = date('Y-m-d H:i:s', strtotime($model->start_at) + 10 * 60);
            }

            if ($model->save()) {
                Yii::app()->user->setFlash('success', 'Poll is updated !');
                $this->redirect(array('view', 'id' => $model->id));
            }
        }
        $this->render('update', array(
            'poll' => $model,
            'voting' => $voting,
            'voted' => $voted,
        ));
    }

    public function actionInvite($poll_id)
    {
        $user_id = $this->current_user->id;
        $poll = Poll::model()->findByPk($poll_id);
        if($user_id == $poll->user_id) {
            $criteria = new CDbCriteria();
            $criteria->condition = 'id!=:user_id';
            $criteria->params = array(':user_id' => $this->current_user->id);
            $users = User::model()->findAll($criteria);
            $this->render('invite', array('users' => $users,
                'poll_id' => $poll_id,
            ));
        }
    }

    public function actionAddinvite($poll_id)
    {
        $user_id = $this->current_user->id;
        $poll = Poll::model()->findByPk($poll_id);
        $users = User::model()->notInvitedTo($poll_id, $user_id)->findAll();
        $this->render('addinvite', array('users' => $users,
            'poll' => $poll,
        ));
    }

    /**
     * @author Nguyen Anh Tien
     */
    public function actionVote($id)
    {
        $poll = Poll::model()->findByPk($id);
        if ($poll != null) {
            // check if user submited form
            if (isset($_POST['choice'])) {
                if ($poll->is_multichoice) {
                    $sumitted_choices = $_POST['choice'];
                } else {
                    $sumitted_choices = array($_POST['choice'] => 1);
                }
                // ensure choice_ids is in poll's choices
                $valid_choice_ids = array();
                foreach ($sumitted_choices as $choice_id => $checked) {
                    if ($poll->hasChoice($choice_id)) {
                        if ($checked) {
                            $valid_choice_ids[] = $choice_id;
                        }
                    } else {
                        // flash invalid choice
                        Yii::app()->user->setFlash('error', 'You selected an invalid choice !');
                        $this->redirect(array('poll/view', 'id' => $id));
                    }
                }
                // log vote type
                $poll->logVoteType($this->current_user);
                // delete previous vote record
                $this->current_user->deleteAllVote($poll->id);

                // must contain only one in case of one-choice
                if (!$poll->is_multichoice && count($valid_choice_ids) > 1) {
                    Yii::app()->user->setFlash('error', 'You can select only one  choice !');
                    $this->redirect(array('poll/view', 'id' => $id));
                }
                // save vote to db
                foreach ($valid_choice_ids as $choice_id) {
                    $vote = new Vote;
                    // ensure current user is voting
                    $vote->user_id = $this->current_user->id;
                    $vote->choice_id = $choice_id;
                    $vote->save();
                    // TODO : add notification
                }
                unset(Yii::app()->session['vote-type']);
           }
           $this->redirect(array('poll/view', 'id' => $id));
        } else {
            $this->redirect(array('poll/index'));
        }
    }
    
    public function actionGetInfo() {
        if (isset($_POST['poll_id'])) {
            $poll = $this->loadModel($_POST['poll_id']);
            if ($poll) {
                echo json_encode($poll->attributes);
            } else {
                throw new CHttpException(500, 'Internal Server Error.');
            }
        } else {
            throw new CHttpException(500, 'Internal Server Error.');
        }
    }
}
?>

