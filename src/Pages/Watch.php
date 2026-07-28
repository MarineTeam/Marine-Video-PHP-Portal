<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Bunny\BunnyService;
use MarineVideoPortal\Models\Video;
use MarineVideoPortal\Core\Database;
class Watch {
  public static function render(string $guid){
    \MarineVideoPortal\Auth\Auth0Service::requireApproved();
    $meta=Video::find($guid); if(!$meta) die('Not found');
    $bunny=new BunnyService(); $embed=$bunny->signedEmbedUrl($guid);
    $title=htmlspecialchars($meta['title']??$guid);
    $related=Database::fetchAll("SELECT * FROM videos_meta WHERE guid!=? ORDER BY custom_order ASC LIMIT 12",[$guid]);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo $title; ?></title><style>body{margin:0;background:#050508;color:#eee;font-family:Inter,system-ui}header{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border-bottom:1px solid rgba(255,255,255,.06)}.layout{display:grid;grid-template-columns:1fr 360px;min-height:calc(100vh - 56px)}@media(max-width:900px){.layout{grid-template-columns:1fr}}.player{aspect-ratio:16/9;background:#000}.player iframe{width:100%;height:100%;border:0}.side{padding:16px;border-left:1px solid rgba(255,255,255,.06);background:#0a0a0e}.rel{display:flex;gap:10px;margin-bottom:10px;text-decoration:none;color:inherit}.rel-thumb{width:120px;aspect-ratio:16/9;background:#14141c;border-radius:8px;overflow:hidden}.rel-thumb img{width:100%;height:100%;object-fit:cover}</style></head><body>
<header><a href="/" style="color:#fff;text-decoration:none">← Back</a><span><?php echo $title; ?></span></header>
<div class="layout"><div><div class="player"><iframe src="<?php echo $embed; ?>" allowfullscreen allow="autoplay; fullscreen"></iframe></div><div style="padding:16px"><h2><?php echo $title; ?></h2></div></div><div class="side"><div style="font-size:11px;color:#666;margin-bottom:8px">UP NEXT</div><?php foreach($related as $r): $rg=htmlspecialchars($r['guid']); $rt=htmlspecialchars($r['title']??$rg); $th=$bunny->thumbnailUrl($r['guid'],86400); ?><a class="rel" href="/watch/<?php echo $rg; ?>"><div class="rel-thumb"><img src="<?php echo $th; ?>" loading="lazy"></div><div style="font-size:13px"><?php echo $rt; ?></div></a><?php endforeach; ?></div></div>
</body></html>
<?php
  }
}
