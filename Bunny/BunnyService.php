<?php
namespace MarineVideoPortal\Bunny;
class BunnyService {
  private string $libId; private string $apiKey; private string $tokenKey; private string $cdnHost;
  public function __construct(){ $this->libId=$_ENV['BUNNY_LIBRARY_ID']??''; $this->apiKey=$_ENV['BUNNY_API_KEY']??''; $this->tokenKey=$_ENV['BUNNY_TOKEN_AUTH_KEY']??''; $this->cdnHost=$_ENV['BUNNY_CDN_HOSTNAME']??''; }
  private function req(string $method,string $path,$body=null){
    $url="https://video.bunnycdn.com/library/{$this->libId}$path";
    $ch=curl_init($url);
    $headers=["AccessKey: {$this->apiKey}","Content-Type: application/json","Accept: application/json"];
    $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CUSTOMREQUEST=>$method];
    if($body!==null) $opts[CURLOPT_POSTFIELDS]=json_encode($body);
    curl_setopt_array($ch,$opts);
    $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code>=400) throw new \Exception("Bunny API $code: $res URL $url");
    return json_decode($res,true)??[];
  }
  public function listVideos(int $page=1,int $perPage=100){ return $this->req('GET',"/videos?page=$page&itemsPerPage=$perPage&orderBy=date"); }
  public function getVideo(string $guid){ return $this->req('GET',"/videos/$guid"); }
  public function deleteVideo(string $guid){ return $this->req('DELETE',"/videos/$guid"); }
  public function listCollections(){ try{ return $this->req('GET',"/collections?page=1&itemsPerPage=100"); }catch(\Throwable $e){ return []; } }
  public function signedEmbedUrl(string $guid,int $ttl=14400): string {
    $exp=time()+$ttl; $token=hash('sha256',$this->tokenKey.$guid.$exp);
    return "https://iframe.mediadelivery.net/embed/{$this->libId}/$guid?token=$token&expires=$exp&autoplay=false";
  }
  public function thumbnailUrl(string $guid,int $ttl=3600): string {
    $exp=time()+$ttl;
    if($this->tokenKey){
      $token=hash('sha256',$this->tokenKey.$guid.$exp);
      if($this->cdnHost){
        return "https://{$this->cdnHost}/{$guid}/thumbnail.jpg?token=$token&expires=$exp";
      }
      return "https://iframe.mediadelivery.net/{$this->libId}/$guid/thumbnail.jpg?token=$token&expires=$exp";
    }
    if($this->cdnHost) return "https://{$this->cdnHost}/{$guid}/thumbnail.jpg";
    return "https://iframe.mediadelivery.net/{$this->libId}/$guid/preview.jpg";
  }
  public function previewUrl(string $guid,int $ttl=3600): string {
    $exp=time()+$ttl;
    if($this->tokenKey){
      $token=hash('sha256',$this->tokenKey.$guid.$exp);
      if($this->cdnHost) return "https://{$this->cdnHost}/{$guid}/preview.webp?token=$token&expires=$exp";
    }
    return $this->thumbnailUrl($guid,$ttl);
  }
  public function syncToDb(): int {
    $count=0; $page=1;
    do{
      $resp=$this->listVideos($page,100);
      $items=$resp['items']??$resp??[]; if(!is_array($items)) break;
      if(empty($items)) break;
      foreach($items as $v){
        $guid=$v['guid']??''; if(!$guid) continue;
        $title=$v['title']??'Untitled';
        $collection=$v['collectionId']??null;
        $exists=\MarineVideoPortal\Core\Database::fetchOne("SELECT guid FROM videos_meta WHERE guid=?",[$guid]);
        if($exists){ \MarineVideoPortal\Core\Database::exec("UPDATE videos_meta SET title=? WHERE guid=?",[$title,$guid]); }
        else { \MarineVideoPortal\Core\Database::exec("INSERT INTO videos_meta (guid,collection_id,title,watermark_mode,custom_order) VALUES (?,?,?,?,0)",[$guid,$collection,$title,'default']); }
        $count++;
      }
      if(count($items)<100) break;
      $page++; if($page>50) break;
    }while(true);
    return $count;
  }
}
