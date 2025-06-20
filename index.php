<?php
/*---------------------------
   一体化 TikTok 无水印下载器
   - 当带 ?aweme_id=XX 请求时，作为后端代理转发到 TikHub
   - 否则输出 HTML 页面供人浏览
----------------------------*/

const API_KEY = 'lrw6TmotwmY2gjtX0zmqvUIV11QahJrlirwixhrguX0ZrFcFbvF97Q6QDA==';
const API_URL = 'https://api.tikhub.io/api/v1/tiktok/app/v3/fetch_one_video';

/* === 代理模式 === */
if (isset($_GET['aweme_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $awemeId = preg_replace('/[^0-9]/', '', $_GET['aweme_id']);
    if (!$awemeId) {
        http_response_code(400);
        echo json_encode(['code' => 400, 'msg' => 'invalid aweme_id']);
        exit;
    }

    $url = API_URL . '?aweme_id=' . $awemeId;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . API_KEY
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code($status ?: 500);
    echo $resp ?: json_encode(['code' => 500, 'msg' => 'empty response']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<meta charset="utf-8" />
<title>抖音 / TikTok 无水印下载</title>
<style>
body{font-family:sans-serif;background:#f7f8fa;display:flex;justify-content:center}
.card{width:460px;margin-top:80px;padding:30px 34px;border-radius:14px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.06)}
h1{font-size:22px;margin:0 0 22px;text-align:center;color:#333}
input,button{width:100%;padding:12px 14px;font-size:15px;border-radius:8px}
input{border:1px solid #ccc}
button{margin-top:14px;border:none;color:#fff;background:#fe2c55}
button:disabled{background:#ffa5b8}
#result{display:none;margin-top:24px}
#result video{width:100%;border-radius:10px}
a.dl{display:inline-block;margin-top:12px;padding:10px 20px;background:#27c34e;color:#fff;border-radius:8px;text-decoration:none}
</style>

<div class="card">
  <h1>TikTok / 抖音无水印下载</h1>
  <input id="url" placeholder="输入 aweme_id 或分享链接">
  <button id="btn">解析</button>

  <div id="result">
    <p id="title"></p>
    <video id="preview" controls></video>
    <a id="dl" class="dl" target="_blank">下载无水印</a>
    <a id="dl2" class="dl" target="_blank" style="background:#03a9f4;display:none;margin-left:8px;">备用下载</a>
    <pre id="rawJson" style="white-space:pre-wrap;background:#f5f5f5;padding:10px;border-radius:6px;margin-top:12px;max-height:260px;overflow:auto"></pre>
    <button id="copyJson" style="margin-top:8px;padding:8px 14px;border:none;border-radius:6px;background:#4caf50;color:#fff;cursor:pointer;">复制 JSON</button>
  </div>
</div>

<script>
const $url=document.getElementById('url');
const $btn=document.getElementById('btn');
const $box=document.getElementById('result');
const $title=document.getElementById('title');
const $preview=document.getElementById('preview');
const $dl=document.getElementById('dl');
const $dl2=document.getElementById('dl2');
const $raw=document.getElementById('rawJson');
const $copyBtn=document.getElementById('copyJson');

$btn.onclick=function(){
  const raw=$url.value.trim();
  if(!raw){alert('请先输入 aweme_id 或链接');return;}

  const m=raw.match(/(?:video\/|aweme_id=)(\d{10,})/);
  const id=m ? m[1] : raw.replace(/[^0-9]/g,'');
  if(!/^\d{6,}$/.test(id)){alert('无法识别 aweme_id');return;}

  toggle(true);

  fetch(location.pathname + '?aweme_id=' + id)
    .then(function(r){
        if(!r.ok) throw new Error('服务器 ' + r.status);
        return r.text();
    })
    .then(function(txt){
        $raw.textContent = txt;
        $box.style.display = 'block';

        // 尝试解析下载地址
        var data;
        try{ data = JSON.parse(txt); }catch(e){ console.warn('JSON parse error'); return; }

        var aweme = data && data.data && data.data.aweme_details && data.data.aweme_details[0];
        if(!aweme){ console.warn('aweme_details not found'); return; }

        var allow = aweme.video_control && aweme.video_control.allow_download;
        if(allow === false){ alert('作者已禁止下载'); return; }

        // 推荐 & 备用地址汇总
        var urlsPrimary = (aweme.video && aweme.video.download_no_watermark_addr && aweme.video.download_no_watermark_addr.url_list) || [];
        var urlsBackup  = (aweme.video && aweme.video.download_addr && aweme.video.download_addr.url_list) || [];
        var vUrl = urlsPrimary.length ? urlsPrimary[0] : (urlsBackup[0] || '');
        if(!vUrl){ console.warn('未找到下载地址'); return; }

        $preview.src = vUrl;
        var name = (aweme.author && (aweme.author.unique_id || aweme.author.nickname) || 'tiktok') + '.mp4';
        $dl.href = vUrl;
        $dl.download = name;
        $dl.textContent = '下载：' + name;

        // 备用下载（取首个不同于主链接的地址）
        var backupUrl = '';
        if(urlsPrimary.length>1){ backupUrl = urlsPrimary[1]; }
        if(!backupUrl && urlsBackup.length){ backupUrl = urlsBackup[0]; }
        if(backupUrl && backupUrl!==vUrl){
            $dl2.href = backupUrl;
            $dl2.download = name;
            $dl2.style.display = 'inline-block';
        } else {
            $dl2.style.display = 'none';
        }
    })
    .catch(function(e){
        $raw.textContent = 'Error: ' + (e.message || e);
        $box.style.display = 'block';
        alert(e.message || e);
    })
    .finally(function(){ toggle(false); });
};

function toggle(disabled){
  $btn.disabled = disabled;
  $btn.textContent = disabled ? '解析中…' : '解析';
}

// 复制 JSON 按钮
$copyBtn.onclick=function(){
  const text=$raw.textContent.trim();
  if(!text){alert('暂无内容');return;}
  if(navigator.clipboard&&window.isSecureContext){
      navigator.clipboard.writeText(text).then(()=>alert('已复制')).catch(()=>fallback(text));
  }else{
      fallback(text);
  }
  function fallback(t){
      const ta=document.createElement('textarea');
      ta.value=t;document.body.appendChild(ta);ta.select();
      try{document.execCommand('copy');alert('已复制');}catch(e){alert('复制失败');}
      document.body.removeChild(ta);
  }
};

// 下载按钮提示
[$dl,$dl2].forEach(function(btn){
  btn.addEventListener('click',function(){
    if(!this.href){alert('暂无可下载链接');return;}
    alert('下载任务已开始，若浏览器未自动保存，请右键链接另存为。');
  });
});
</script>
</html>