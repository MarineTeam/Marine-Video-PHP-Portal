<?php
namespace MarinePortal\Video;
use GuzzleHttp\Client;
class BunnyService { private Client $client; public function __construct(private array $cfg){ $this->client=new Client(['base_uri'=>'https://video.bunnycdn.com/','headers'=>['AccessKey'=>$cfg['api_key']]]); } public function listVideos(int $page=1,int $per=100): array { $r=$this->client->get("library/{$this->cfg['library_id']}/videos",['query'=>['page'=>$page,'itemsPerPage'=>$per]]); return json_decode((string)$r->getBody(),true); } public function getSignedEmbedUrl(string $guid,int $exp=3600): string { $t=time()+$exp; $tok=hash('sha256',$this->cfg['token_auth_key'].$guid.$t); return "https://iframe.mediadelivery.net/embed/{$this->cfg['library_id']}/$guid?token=$tok&expires=$t"; } }
