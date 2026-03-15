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
<!-- <button onclick="cancelCurrentUpload()" style="margin-top:10px;background:#dc3545;">
Cancel Upload
</button> -->
<br><br>

<button onclick="pauseUpload()" style="background:#ffc107;color:black;">Pause</button>

<button onclick="resumeUpload()" style="background:#28a745;">Resume</button>

<button onclick="cancelCurrentUpload()" style="background:#dc3545;">Cancel</button>

<div id="chunkInfo" style="margin-top:10px;font-size:14px;color:#444;"></div>

<div id="speedInfo" style="margin-top:5px;font-size:14px;color:#444;"></div>
</div>

<script>

const chunkSize = 15 * 1024 * 1024;
let cancelUpload = false;
let paused = false;
let currentChunkIndex = 0;
let startTime = 0;
async function startUpload(){

const parallelUploads = 4;
startTime = Date.now();
const file = document.getElementById("fileInput").files[0];

if(!file){
alert("Please select a file");
return;
}

cancelUpload = false;

document.getElementById("statusText").innerText = "Initializing upload...";
document.getElementById("loader").style.display = "block";

/* CHECK IF RESUME SESSION EXISTS */

let session = JSON.parse(localStorage.getItem("uploadSession"));

let uploadId;
let key;
let parts = [];

if(session && session.fileName === file.name){

uploadId = session.uploadId;
key = session.key;
parts = session.parts || [];

document.getElementById("statusText").innerText = "Resuming previous upload...";

}else{

/* INIT UPLOAD */

const initRes = await fetch("/upload/init",{
method:"POST",
headers:{
"Content-Type":"application/json",
"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content
},
body:JSON.stringify({
file_name:file.name
})
});

const initData = await initRes.json();

uploadId = initData.uploadId;
key = initData.key;

localStorage.setItem("uploadSession",JSON.stringify({
fileName:file.name,
uploadId:uploadId,
key:key,
parts:[]
}));

}

const totalChunks = Math.ceil(file.size / chunkSize);

for(let i=0;i<totalChunks;i++){

currentChunkIndex = i;

if(paused){
document.getElementById("statusText").innerText="Upload paused...";
return;
}

showChunkNumber(i+1,totalChunks);

calculateSpeed((i+1)*chunkSize);

if(cancelUpload){
document.getElementById("statusText").innerText = "Upload cancelled";
return;
}

/* SKIP ALREADY UPLOADED PART */

let uploadedPartNumbers = parts.map(p=>p.PartNumber);

if(uploadedPartNumbers.includes(i+1)){
continue;
}

const start = i * chunkSize;
const end = Math.min(file.size,start+chunkSize);

const chunk = file.slice(start,end);

/* GET PRESIGNED URL */

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

/* UPLOAD CHUNK WITH RETRY */

const upload = await uploadChunkWithRetry(urlData.url,chunk);

const etag = upload.headers.get("ETag");

parts.push({
PartNumber:i+1,
ETag:etag
});

/* SAVE SESSION */

localStorage.setItem("uploadSession",JSON.stringify({
fileName:file.name,
uploadId:uploadId,
key:key,
parts:parts
}));

/* UPDATE PROGRESS */

let percent = Math.floor(((i+1)/totalChunks)*100);

document.getElementById("progressBar").style.width = percent+"%";
document.getElementById("progressText").innerText = percent+"%";

document.getElementById("statusText").innerText = "Uploading chunk "+(i+1)+" of "+totalChunks;

}

/* COMPLETE MULTIPART */

document.getElementById("statusText").innerText = "Finalizing upload...";
parts.sort((a,b)=>a.PartNumber-b.PartNumber);
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

/* CLEAR SESSION */

localStorage.removeItem("uploadSession");

document.getElementById("loader").style.display="none";
document.getElementById("statusText").innerText="Upload completed successfully";

alert("Upload Completed!");

}

/* RETRY SYSTEM */

async function uploadChunkWithRetry(url,chunk,retries=3){

for(let attempt=1;attempt<=retries;attempt++){

try{

const response = await fetch(url,{
method:"PUT",
headers:{
"Content-Type":"application/octet-stream"
},
body:chunk
});
if(response.ok){
return response;
}

}catch(error){

console.log("Retry attempt:",attempt);

}

await new Promise(resolve=>setTimeout(resolve,2000));

}

throw new Error("Chunk upload failed");

}

/* CANCEL UPLOAD */

function cancelCurrentUpload(){

cancelUpload = true;

document.getElementById("loader").style.display="none";

document.getElementById("statusText").innerText="Upload cancelled by user";

}

function pauseUpload(){

paused = true;

document.getElementById("statusText").innerText = "Upload paused";

}

function resumeUpload(){

paused = false;

document.getElementById("statusText").innerText = "Resuming upload...";

startUpload();

}

function calculateSpeed(uploadedBytes){

let time = (Date.now() - startTime) / 1000;

let speed = uploadedBytes / time;

let speedMB = (speed / (1024*1024)).toFixed(2);

document.getElementById("speedInfo").innerText =
"Upload Speed: " + speedMB + " MB/s";

}

function showChunkNumber(current,total){

document.getElementById("chunkInfo").innerText =
"Uploading Chunk: " + current + " / " + total;

}

async function uploadSingleChunk(i){

const start = i * chunkSize;
const end = Math.min(file.size,start+chunkSize);
const chunk = file.slice(start,end);

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

const upload = await uploadChunkWithRetry(urlData.url,chunk);

const etag = upload.headers.get("ETag");

parts.push({
PartNumber:i+1,
ETag:etag
});

}

async function uploadChunkParallel(i){

showChunkNumber(i+1,totalChunks);

calculateSpeed((i+1)*chunkSize);

/* SKIP ALREADY UPLOADED PART */

let uploadedPartNumbers = parts.map(p=>p.PartNumber);

if(uploadedPartNumbers.includes(i+1)){
return;
}

const start = i * chunkSize;
const end = Math.min(file.size,start+chunkSize);

const chunk = file.slice(start,end);

/* GET PRESIGNED URL */

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

/* UPLOAD CHUNK WITH RETRY */

const upload = await uploadChunkWithRetry(urlData.url,chunk);

const etag = upload.headers.get("ETag");

parts.push({
PartNumber:i+1,
ETag:etag
});

/* SAVE SESSION */

localStorage.setItem("uploadSession",JSON.stringify({
fileName:file.name,
uploadId:uploadId,
key:key,
parts:parts
}));

/* UPDATE PROGRESS */

let percent = Math.floor((parts.length/totalChunks)*100);

document.getElementById("progressBar").style.width = percent+"%";
document.getElementById("progressText").innerText = percent+"%";

document.getElementById("statusText").innerText =
"Uploading chunk "+parts.length+" of "+totalChunks;

}
</script>
</body>
</html>