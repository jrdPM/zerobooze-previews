<?php
declare(strict_types=1);
// Kept inside the hub folder; secrets belong in the application's private_html.
// Secrets live in the private_html folder next to the public_html that serves this hub, whether the hub
// sits directly in public_html or inside a project folder. Anchoring on public_html means a stray
// private_html folder inside the web root is never used.
function hub_private_dir(): string {
    $d = __DIR__;
    while (dirname($d) !== $d) {
        if (basename($d) === 'public_html') return dirname($d) . '/private_html';
        $d = dirname($d);
    }
    return dirname(__DIR__, 2) . '/private_html';
}
function hub_config(): array {
    static $c;
    if ($c !== null) return $c;
    $path = hub_private_dir() . '/zerobooze-content-hub.php';
    if (!is_file($path)) throw new RuntimeException('Hub feedback is not configured.');
    $c = require $path;
    if (!is_array($c) || strlen((string)($c['app_key'] ?? '')) < 32 || empty($c['base_url']) || empty($c['admin_hash'])) throw new RuntimeException('Invalid hub configuration.');
    return $c;
}
function hub_headers(): void {
    header('Cache-Control: no-store, private, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
}
function hub_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $c = hub_config();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('zb_hub_review');
    session_set_cookie_params(['lifetime'=>0, 'path'=>parse_url($c['base_url'], PHP_URL_PATH) ?: '/', 'secure'=>str_starts_with($c['base_url'], 'https://'), 'httponly'=>true, 'samesite'=>'Strict']);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    if (isset($_SESSION['admin_at']) && (time() - (int)$_SESSION['admin_at'] > 7200 || !hash_equals(hash('sha256',$c['app_key']),(string)($_SESSION['admin_version']??'')))) unset($_SESSION['admin_at'],$_SESSION['admin_version']);
}
function hub_db(): PDO {
    static $db;
    if ($db) return $db;
    $c=hub_config();
    $db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}
function hub_reply(array $data, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function hub_require_admin(): void {
    if (empty($_SESSION['admin_at'])) hub_reply(['error'=>'Sign in to the review dashboard.'],401);
}
function hub_ip_key(string $purpose): string {
    return hash_hmac('sha256',$purpose.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),hub_config()['app_key']);
}
function hub_rate(string $purpose, int $limit, int $seconds): void {
    $db=hub_db();$key=hub_ip_key($purpose);$bucket=(int)floor(time()/$seconds);
    $q=$db->prepare('INSERT INTO zb_hub_rate (rate_key,bucket,hits) VALUES (?,?,1) ON DUPLICATE KEY UPDATE hits=hits+1');$q->execute([$key,$bucket]);
    $q=$db->prepare('SELECT hits FROM zb_hub_rate WHERE rate_key=? AND bucket=?');$q->execute([$key,$bucket]);
    if ((int)$q->fetchColumn()>$limit) hub_reply(['error'=>'Please wait a little before trying again.'],429);
    // Bounded opportunistic cleanup; no permanent raw-IP log.
    if(random_int(1,30)===1){$q=$db->prepare('DELETE FROM zb_hub_rate WHERE rate_key=? AND bucket < ? LIMIT 500');$q->execute([$key,$bucket-(int)ceil(172800/$seconds)]);}
}
function hub_input(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') hub_reply(['error'=>'POST required.'],405);
    $origin=$_SERVER['HTTP_ORIGIN'] ?? ''; $c=hub_config();
    $expected=parse_url($c['base_url'],PHP_URL_SCHEME).'://'.parse_url($c['base_url'],PHP_URL_HOST);
    if(parse_url($c['base_url'],PHP_URL_PORT)) $expected.=':'.parse_url($c['base_url'],PHP_URL_PORT);
    if ($origin!=='' && !hash_equals($expected,$origin)) hub_reply(['error'=>'Invalid request origin.'],403);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>100000) hub_reply(['error'=>'Submission too large.'],413);
    $raw=file_get_contents('php://input',false,null,0,100001);
    if($raw===false || strlen($raw)>100000) hub_reply(['error'=>'Submission too large.'],413);
    $v=json_decode($raw,true);
    if(!is_array($v)) hub_reply(['error'=>'Invalid request.'],400);
    if(!hash_equals($_SESSION['csrf'],(string)($v['csrf']??''))) hub_reply(['error'=>'Please refresh the page and try again.'],403);
    return $v;
}
function hub_text(mixed $v,int $max,bool $required=true): string {
    if(!is_string($v)) hub_reply(['error'=>'Invalid text field.'],422);
    $v=trim($v);
    if(($required&&$v==='')||strlen($v)>$max||!preg_match('//u',$v)) hub_reply(['error'=>'Check the required fields and text length.'],422);
    return $v;
}
function hub_catalog(): array {
    static $catalog;
    if($catalog!==null)return $catalog;
    $data=json_decode(file_get_contents(__DIR__.'/hub-data.json'),true,512,JSON_THROW_ON_ERROR);
    $resources=[];$questions=[];
    foreach($data['items'] as $item){$resources[$item['id']]=$item['title'];if($item['type']==='Questionnaire'){foreach(explode("\n",$item['body'])as $line){if(str_contains($line,'[________________]'))$questions[] = explode(': [',$line)[0];}}}
    return $catalog=['resources'=>$resources,'questions'=>$questions];
}
function hub_filter(): array {
    $where=[];$params=[];
    $status=$_GET['status']??'';
    if(in_array($status,['open','resolved'],true)){$where[]='status=?';$params[]=$status;}
    $kind=$_GET['kind']??'';
    if(in_array($kind,['comment','answer'],true)){$where[]='kind=?';$params[]=$kind;}
    $search=trim((string)($_GET['q']??''));
    if($search!==''){$where[]='(resource_title LIKE ? OR reviewer_name LIKE ? OR body LIKE ?)';for($i=0;$i<3;$i++)$params[]='%'.substr($search,0,200).'%';}
    return [$where?' WHERE '.implode(' AND ',$where):'',$params];
}
