<!DOCTYPE html>
<html>
<head>
<title>Video Upload</title>

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>

body{
font-family:Arial;
background:#f4f6f9;
display:flex;
justify-content:center;
align-items:center;
height:100vh;
}

.container{
background:white;
padding:40px;
width:420px;
border-radius:10px;
box-shadow:0 8px 25px rgba(0,0,0,0.1);
text-align:center;
}

button{
background:#007bff;
color:white;
border:none;
padding:10px 20px;
border-radius:6px;
cursor:pointer;
margin:5px;
}

#progressContainer{
width:100%;
height:22px;
background:#e0e0e0;
border-radius:20px;
margin-top:20px;
overflow:hidden;
}

#progressBar{
height:100%;
width:0%;
background:linear-gradient(90deg,#00c6ff,#0072ff);
}

.loader{
margin:20px auto;
border:6px solid #f3f3f3;
border-top:6px solid #007bff;
border-radius:50%;
width:40px;
height:40px;
animation:spin 1s linear infinite;
display:none;
}

@keyframes spin{
0%{transform:rotate(0deg);}
100%{transform:rotate(360deg);}
}

.status{
margin-top:10px;
}

</style>
</head>

<body>

<div class="container">

<h2>Upload Video</h2>

<input type="file" id="fileInput">

<br>

<button onclick="startUpload()">Upload</button>
<button onclick="pauseUpload()" style="background:#ffc107;">Pause</button>
<button onclick="resumeUpload()" style="background:#28a745;">Resume</button>

<div id="progressContainer">
<div id="progressBar"></div>
</div>

<div id="progressText">0%</div>

<div class="loader" id="loader"></div>

<div class="status" id="statusText"></div>
<div class="status" id="speedText"></div>

</div>

<script>

const chunkSize = 5 * 1024 * 1024;
const MAX_PARALLEL_UPLOADS = 4;

let pause=false;
let cancelUpload=false;

let globalFile;
let uploadId;
let key;
let parts=[];

async function startUpload(){

const file=document.getElementById("fileInput").files[0];

if(!file){
alert("Select file");
return;
}

globalFile=file;

document.getElementById("loader").style.display="block";

const initRes=await fetch("/upload/init",{
method:"POST",
headers:{
"Content-Type":"application/json",
"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content
},
body:JSON.stringify({file_name:file.name})
});

const initData=await initRes.json();

uploadId=initData.uploadId;
key=initData.key;

uploadChunks();
}

async function uploadChunks(){

const file=globalFile;
const totalChunks=Math.ceil(file.size/chunkSize);

let activeUploads=[];
let uploadedCount=0;

for(let i=0;i<totalChunks;i++){

if(pause)return;

const start=i*chunkSize;
const end=Math.min(file.size,start+chunkSize);

const chunk=file.slice(start,end);

const uploadPromise=uploadSingleChunk(chunk,i,totalChunks)
.then(()=>{

uploadedCount++;

let percent=Math.floor((uploadedCount/totalChunks)*100);

document.getElementById("progressBar").style.width=percent+"%";
document.getElementById("progressText").innerText=percent+"%";

});

activeUploads.push(uploadPromise);

if(activeUploads.length>=MAX_PARALLEL_UPLOADS){

await Promise.race(activeUploads);

activeUploads=activeUploads.filter(p=>!p.resolved);

}

}

await Promise.all(activeUploads);

/* IMPORTANT FIX */

parts.sort((a,b)=>a.PartNumber-b.PartNumber);

/* COMPLETE MULTIPART */

await fetch("/upload/complete",{
method:"POST",
headers:{
"Content-Type":"application/json",
"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content
},
body:JSON.stringify({
uploadId,
key,
parts
})
});

document.getElementById("loader").style.display="none";
document.getElementById("statusText").innerText="Upload Completed";

alert("Upload Complete");

}

async function uploadSingleChunk(chunk,i,totalChunks){

let startTime=Date.now();

/* PRESIGNED URL */

const urlRes=await fetch("/s3-presigned-url",{
method:"POST",
headers:{
"Content-Type":"application/json",
"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content
},
body:JSON.stringify({
uploadId,
key,
partNumber:i+1
})
});

const urlData=await urlRes.json();

/* UPLOAD */

const upload=await fetch(urlData.url,{
method:"PUT",
body:chunk
});

let endTime=Date.now();

let seconds=(endTime-startTime)/1000;
let speed=(chunk.size/1024/1024)/seconds;

document.getElementById("speedText").innerText=
"Speed: "+speed.toFixed(2)+" MB/s";

const etag=upload.headers.get("ETag");

parts.push({
PartNumber:i+1,
ETag:etag
});

document.getElementById("statusText").innerText=
"Uploading chunk "+(i+1)+" / "+totalChunks;

}

function pauseUpload(){

pause=true;
document.getElementById("statusText").innerText="Upload Paused";

}

function resumeUpload(){

pause=false;
uploadChunks();

}

</script>

</body>
</html>
