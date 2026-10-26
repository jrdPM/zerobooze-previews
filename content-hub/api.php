<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
hub_headers();
try {
    hub_session();$action=$_GET['action']??'session';
    if($action==='session'){
        // Check storage before telling a reviewer that server saving is available.
        hub_db()->query('SELECT 1 FROM zb_hub_feedback LIMIT 1');
        hub_reply(['csrf'=>$_SESSION['csrf'],'ready'=>true,'admin'=>!empty($_SESSION['admin_at'])]);
    }
    if($action==='submit'){
        $v=hub_input();if(!empty($v['website']))hub_reply(['error'=>'Unable to accept this submission.'],422);
        hub_rate('submit',30,3600);
        $name=hub_text($v['name']??'',120);$email=hub_text($v['email']??'',254,false);
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))hub_reply(['error'=>'Check your email address.'],422);
        $request=hub_text($v['request_id']??'',36);
        if(!preg_match('/^[a-f0-9-]{36}$/i',$request))hub_reply(['error'=>'Invalid submission ID.'],422);
        $kind=$v['kind']??'';$catalog=hub_catalog();$rows=[];
        if($kind==='comment'){
            $id=hub_text($v['resource_id']??'',80);
            if(!isset($catalog['resources'][$id]))hub_reply(['error'=>'Unknown content section.'],422);
            $rows[]=['resource_id'=>$id,'title'=>$catalog['resources'][$id],'body'=>hub_text($v['body']??'',10000),'key'=>$request];
        }elseif($kind==='answers'){
            $answers=$v['answers']??null;
            if(!is_array($answers)||count($answers)>count($catalog['questions']))hub_reply(['error'=>'Invalid questionnaire.'],422);
            foreach($answers as $idx=>$answer){
                if(!ctype_digit((string)$idx)||!isset($catalog['questions'][(int)$idx]))hub_reply(['error'=>'Unknown question.'],422);
                $text=hub_text($answer,5000,false);if($text==='')continue;
                $rows[]=['resource_id'=>'question-'.(int)$idx,'title'=>$catalog['questions'][(int)$idx],'body'=>$text,'key'=>$request.'-q'.(int)$idx];
            }
            if(!$rows)hub_reply(['error'=>'Add at least one answer before sending.'],422);
            $kind='answer';
        }else hub_reply(['error'=>'Unknown submission type.'],422);
        $db=hub_db();$db->beginTransaction();$ids=[];
        try{
            $insert=$db->prepare('INSERT INTO zb_hub_feedback (request_key,resource_id,resource_title,kind,reviewer_name,reviewer_email,body,status) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
            foreach($rows as $r){$insert->execute([$r['key'],$r['resource_id'],$r['title'],$kind,$name,$email,$r['body'],'open']);$ids[]=(int)$db->lastInsertId();}
            $db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
        hub_reply(['ok'=>true,'ids'=>$ids,'message'=>'Saved for the ZeroBooze team.'],201);
    }
    if($action==='login'){
        $v=hub_input();hub_rate('login',8,900);
        if(!password_verify((string)($v['password']??''),hub_config()['admin_hash']))hub_reply(['error'=>'The dashboard password was not accepted.'],401);
        session_regenerate_id(true);$_SESSION['admin_at']=time();$_SESSION['admin_version']=hash('sha256',hub_config()['app_key']);$_SESSION['csrf']=bin2hex(random_bytes(32));hub_reply(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
    }
    if($action==='logout'){
        hub_input();unset($_SESSION['admin_at'],$_SESSION['admin_version']);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));hub_reply(['ok'=>true]);
    }
    if($action==='list'){
        hub_require_admin();[$where,$params]=hub_filter();$page=max(1,min(100000,(int)($_GET['page']??1)));$offset=($page-1)*50;
        $q=hub_db()->prepare('SELECT COUNT(*) FROM zb_hub_feedback'.$where);$q->execute($params);$total=(int)$q->fetchColumn();
        $q=hub_db()->prepare('SELECT id,resource_id,resource_title,kind,reviewer_name,reviewer_email,body,status,created_at,updated_at FROM zb_hub_feedback'.$where.' ORDER BY id DESC LIMIT 50 OFFSET '.$offset);$q->execute($params);
        hub_reply(['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page]);
    }
    if($action==='resolve'){
        hub_require_admin();$v=hub_input();$id=(int)($v['id']??0);$status=$v['status']??'';
        if($id<1||!in_array($status,['open','resolved'],true))hub_reply(['error'=>'Invalid status.'],422);
        $q=hub_db()->prepare('UPDATE zb_hub_feedback SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');$q->execute([$status,$id]);hub_reply(['ok'=>true]);
    }
    if($action==='export'){
        hub_require_admin();[$where,$params]=hub_filter();$q=hub_db()->prepare('SELECT id,resource_title,kind,reviewer_name,reviewer_email,body,status,created_at,updated_at FROM zb_hub_feedback'.$where.' ORDER BY id DESC');$q->execute($params);
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="ZeroBooze-Feedback-'.gmdate('Y-m-d').'.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['ID','Section / question','Type','Name','Email','Feedback / answer','Status','Created UTC','Updated UTC']);
        while($row=$q->fetch(PDO::FETCH_NUM)){foreach($row as &$cell){$cell=(string)$cell;if(preg_match('/^[\s]*[=+@-]/u',$cell))$cell="'".$cell;}unset($cell);fputcsv($out,$row);}fclose($out);exit;
    }
    hub_reply(['error'=>'Unknown action.'],404);
}catch(Throwable $e){error_log('ZeroBooze hub endpoint failed: '.get_class($e));hub_reply(['error'=>'Shared feedback is not available right now. Your draft has not been sent. Please try again later.'],503);}
