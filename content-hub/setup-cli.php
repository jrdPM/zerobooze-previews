<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function ask(string $label,bool $secret=false): string {
    fwrite(STDOUT,$label.': ');
    if($secret && function_exists('shell_exec'))shell_exec('stty -echo 2>/dev/null');
    $s=trim((string)fgets(STDIN));
    if($secret){if(function_exists('shell_exec'))shell_exec('stty echo 2>/dev/null');fwrite(STDOUT,"\n");}
    return $s;
}
$private=dirname(__DIR__,2).'/private_html';foreach([2,3,4]as$up){if(is_dir(dirname(__DIR__,$up).'/private_html')){$private=dirname(__DIR__,$up).'/private_html';break;}}
if(!is_dir($private)&&!mkdir($private,0700,true)){fwrite(STDERR,"Cannot create private configuration folder.\n");exit(1);}
$target=$private.'/zerobooze-content-hub.php';
if(is_file($target)){fwrite(STDERR,"Configuration already exists. Edit it deliberately; this installer will not overwrite it.\n");exit(1);}
$name=ask('Cloudways application database name');$user=ask('Database username');$password=ask('Database password',true);
$admin=ask('Choose a dashboard password (12–72 characters)',true);$confirm=ask('Confirm dashboard password',true);
if(!preg_match('/^[a-zA-Z0-9_]+$/',$name)||$user===''||$password===''||strlen($admin)<12||strlen($admin)>72||!hash_equals($admin,$confirm)){fwrite(STDERR,"Check the inputs. No configuration was created.\n");exit(1);}
$dsn='mysql:host=localhost;port=3306;dbname='.$name.';charset=utf8mb4';
try{
 $db=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $sql= <<<'ZB_SCHEMA'
-- Additive only. Does not alter any existing application tables.
CREATE TABLE IF NOT EXISTS zb_hub_feedback (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_key VARCHAR(48) CHARACTER SET ascii NOT NULL UNIQUE,
 resource_id VARCHAR(80) NOT NULL,
 resource_title VARCHAR(500) NOT NULL,
 kind VARCHAR(16) NOT NULL,
 reviewer_name VARCHAR(120) NOT NULL,
 reviewer_email VARCHAR(254) NOT NULL DEFAULT '',
 body TEXT NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'open',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_status_created (status,created_at), INDEX idx_resource (resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS zb_hub_rate (
 rate_key CHAR(64) CHARACTER SET ascii NOT NULL,
 bucket BIGINT NOT NULL,
 hits INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (rate_key,bucket)
) ENGINE=InnoDB;

ZB_SCHEMA;
$db->exec($sql);
 $config=['base_url'=>'https://concepts.jumpingjackrabbit.com/zerobooze-previews/content-hub/','dsn'=>$dsn,'db_user'=>$user,'db_password'=>$password,'app_key'=>bin2hex(random_bytes(32)),'admin_hash'=>password_hash($admin,PASSWORD_DEFAULT)];
 $tmp=$target.'.'.bin2hex(random_bytes(4)).'.tmp';$old=umask(0077);file_put_contents($tmp,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX);chmod($tmp,0600);rename($tmp,$target);umask($old);
 fwrite(STDOUT,"Ready. Open /zerobooze-previews/content-hub/ to test feedback and /zerobooze-previews/content-hub/admin.php for the review dashboard.\nThe existing design folders were not changed.\n");
}catch(Throwable $e){fwrite(STDERR,"Setup failed. Verify database access and the PHP PDO MySQL extension. No configuration was saved.\n");exit(1);}
