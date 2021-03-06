<?php

class NotificationController extends Controller
{
    /**
     * @author Nguyen Anh Tien
     */
    public function actionGetNotify()
    {
        
        if (!Yii::app()->request->isAjaxRequest) {
            $this->render('/site/error', array(
                'code' => 403,
                'message' => 'Forbidden'
            ));
            Yii::app()->end();
        }
         
        if (isset($_GET['notify_id']) && isset($_GET['all']) && $_GET['all'] === 'true') {
            $notifications = $this->current_user->getRecentNotifications($_GET['notify_id']);
        } else {
            $notifications = $this->current_user->notifications_received(
                'notifications_received:recently'
            );
        }
        if (!empty($notifications)) {
            $result = array();
            foreach ($notifications as $notification) {
                $activities_array = array();
                foreach ($notification->activities as $activity) {
                    $activities_array[] = $activity->getJSON(null, true);
                }
                $result[] = array(
                    'id' => $notification->id,
                    'viewed' => $notification->viewed,
                    'poll_id' => $notification->poll_id,
                    'activities' => $activities_array,
                );
            }
            echo json_encode($result);
        } else {
            echo json_encode(array());
        }
    }

    public function actionIndex(){
        $this->render('index');
    }

}

