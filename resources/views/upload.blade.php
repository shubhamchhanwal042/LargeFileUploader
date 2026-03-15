

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

h2{ margin-bottom:20px; }

input[type=file]{ margin-bottom:20px; }

button{
    background:#007bff;
    color:white;
    border:none;
    padding:10px 20px;
    border-radius:6px;
    cursor:pointer;
}

button:hover{ background:#0056b3; }

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
    transition:width .3s ease;
}

#progressText{
    margin-top:10px;
    font-weight:bold;
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
    color:#555;
}

</style>
</head>

<body>

<div class="container">

<h2>Upload Video</h2>

<input type="file" id="fileInput">

<br>

<button onclick="startUpload()">Upload</button>

<div id="progressContainer">
<div id="progressBar"></div>
</div>

<div id="progressText">0%</div>

<div class="loader" id="loader"></div>

<div class="status" id="statusText"></div>
<button onclick="startUpload()">Upload</button>
    <button onclick="pauseUpload()" style="background:#ffc107;margin-left:5px;"> Pause </button>
    <button onclick="resumeUpload()" style="background:#28a745;margin-left:5px;"> Resume </button>
</div>
    <div class="status" id="speedText"></div>

<script>

const chunkSize = 5 * 1024 * 1024;

let cancelUpload = false;
let pause = false;

let currentChunk = 0;
let globalFile;
let uploadId;
let key;
let parts = [];

async function startUpload(){

const file = document.getElementById("fileInput").files[0];

if(!file){
alert("Please select file");
return;
}

globalFile = file;
cancelUpload = false;
pause = false;

document.getElementById("loader").style.display="block";

let session = JSON.parse(localStorage.getItem("uploadSession"));

if(session && session.fileName === file.name){

uploadId = session.uploadId;
key = session.key;
parts = session.parts || [];

document.getElementById("statusText").innerText="Resuming previous upload";

}else{

const initRes = await fetch("/upload/init",{
method:"POST",
headers:{
"Content-Type":"application/json",
"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content
},
body:JSON.stringify({ file_name:file.name })
});

const initData = await initRes.json();

uploadId = initData.uploadId;
key = initData.key;

parts = [];

localStorage.setItem("uploadSession",JSON.stringify({
fileName:file.name,
uploadId:uploadId,
key:key,
parts:[]
}));

}

uploadChunks();
}

async function uploadChunks(){

const file = globalFile;
const totalChunks = Math.ceil(file.size / chunkSize);

for(let i=currentChunk;i<totalChunks;i++){

if(cancelUpload) return;

if(pause){
currentChunk = i;
return;
}

let startTime = Date.now();

const start = i * chunkSize;
const end = Math.min(file.size,start+chunkSize);

const chunk = file.slice(start,end);

/* PRESIGNED URL */

const urlRes = await fetch("/s3-presigned-url",{
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

const urlData = await urlRes.json();

/* UPLOAD */

const upload = await fetch(urlData.url,{
method:"PUT",
body:chunk
});

const endTime = Date.now();

let seconds = (endTime-startTime)/1000;
let speed = (chunk.size/1024/1024)/seconds;

document.getElementById("speedText").innerText =
"Speed: "+speed.toFixed(2)+" MB/s";

const etag = upload.headers.get("ETag");

parts.push({
PartNumber:i+1,
ETag:etag
});

/* SAVE SESSION */

localStorage.setItem("uploadSession",JSON.stringify({
fileName:file.name,
uploadId,
key,
parts
}));

/* PROGRESS */

let percent = Math.floor(((i+1)/totalChunks)*100;

document.getElementById("progressBar").style.width=percent+"%";
document.getElementById("progressText").innerText=percent+"%";

document.getElementById("statusText").innerText =
"Uploading chunk "+(i+1)+" / "+totalChunks;

}

/* COMPLETE */

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

localStorage.removeItem("uploadSession");

document.getElementById("loader").style.display="none";

alert("Upload Complete");

}

/* PAUSE */

function pauseUpload(){

pause = true;

document.getElementById("statusText").innerText="Upload Paused";

}

/* RESUME */

function resumeUpload(){

pause = false;

document.getElementById("statusText").innerText="Resuming Upload";

uploadChunks();

}

</script>
