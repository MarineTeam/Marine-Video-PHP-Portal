<?php
namespace MarinePortal\Video;
use GuzzleHttp\Client;
class BunnyService {
    private Client $client;
    public function __construct(private array $cfg){ $this->client=new Client(['base_uri'=>'https://video.bunnycdn.com/','headers'=>['AccessKey'=>$cfg['api_key']]]); }
    public function listVideos(int $page=1,int $perPage=100): array {
        $res=$this->client->get("library/{$this->cfg['library_id']}/videos",['query'=>['page'=>$page,'itemsPerPage'=>$perPage]]);
        return json_decode((string)$res->getBody(),true);
    }
    public function createVideo(string $title,string $collectionId=''): array {
        $res=$this->client->post("library/{$this->cfg['library_id']}/videos",['json'=>['title'=>$title,'collectionId'=>$collectionId]]);
        return json_decode((string)$res->getBody(),true);
    }
    public function deleteVideo(string $guid): bool { try{$this->client->delete("library/{$this->cfg['library_id']}/videos/$guid"); return true;}catch(\Throwable){return false;} }
    public function getSignedEmbedUrl(string $guid, int $expires=3600): string {
        $expiresTime=time()+$expires;
        $token=hash('sha256',$this->cfg['token_auth_key'].$guid.$expiresTime);
        return "https://iframe.mediadelivery.net/embed/{$this->cfg['library_id']}/$guid?token=$token&expires=$expiresTime";
    }
    public function getSignedThumbnailUrl(string $guid): string {
        if(empty($this->cfg['cdn_hostname'])) return '';
        $expires=time()+3600;
        $path="/$guid/thumbnail.jpg";
        $key=$this->cfg['cdn_token_key'] ?: $this->cfg['token_auth_key'];
        $token=hash('sha256',"$key$path$expires");
        return "https://{$this->cfg['cdn_hostname']}$path?token=$token&expires=$expires";
    }
    public function signTusUpload(string $videoId,int $expires=3600): array {
        $expiresTime=time()+$expires;
        $signature=hash('sha256',$this->cfg['library_id'].$this->cfg['token_auth_key'].$expiresTime.$videoId);
        return ['signature'=>$signature,'expires'=>$expiresTime,'library_id'=>$this->cfg['library_id'],'video_id'=>$videoId];
    }
}
