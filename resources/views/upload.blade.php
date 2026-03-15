```html
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
</style>
</head>

<body>

<div class="container">

<h2>Upload Video</h2>

<input type="file" id="fileInput"><br><br>

<button onclick="startUpload()">Upload</button>
<button onclick="pauseUpload()">Pause</button>
<button onclick="resumeUpload()">Resume</button>
<button onclick="cancelCurrentUpload()">Cancel</button>

<div id="progressContainer">
<div id="progressBar"></div>
</div>

<div id="progressText">0%</div>

<div class="loader" id="loader"></div>

<div id="statusText"></div>
<div id="chunkInfo"></div>
<div id="speedInfo"></div>

</div>

<script>

const chunkSize = 15 * 1024 * 1024;
const parallelUploads = 4;

let cancelUpload=false;
let paused=false;
let startTime=0;

let file;
let totalChunks;
let uploadId;
let key;
let parts=[];

async function startUpload(){

file=document.getElementById("fileInput").files[0];

if(!file){
alert("Select file");
return;
}

startTime=Date.now();
cancelUpload=false;
paused=false;

document.getElementById("loader").style.display="block";
document.getElementById("statusText").innerText="Initializing upload...";

let session=JSON.parse(localStorage.getItem("uploadSession"));

if(session && session.fileName===file.name){

uploadId=session.uploadId;
key=session.key;
parts=session.parts || [];

}else{

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

parts=[];

localStorage.setItem("uploadSession",JSON.stringify({
fileName:file.name,
uploadId,
key,
parts
}));

}

totalChunks=Math.ceil(file.size/chunkSize);

await uploadChunksParallel();

await completeUpload();

}

async function uploadChunksParallel(){

let queue=[];

for(let i=0;i<totalChunks;i++){

if(cancelUpload){
document.getElementById("statusText").innerText="Upload cancelled";
return;
}

if(paused){
document.getElementById("statusText").innerText="Upload paused";
return;
}

queue.push(uploadChunk(i));

if(queue.length===parallelUploads){

await Promise.all(queue);
queue=[];

}

}

if(queue.length){
await Promise.all(queue);
}

}

async function uploadChunk(i){

showChunkNumber(i+1,totalChunks);
calculateSpeed((i+1)*chunkSize);

let uploadedPartNumbers=parts.map(p=>p.PartNumber);

if(uploadedPartNumbers.includes(i+1)){
return;
}

const start=i*chunkSize;
const end=Math.min(file.size,start+chunkSize);
const chunk=file.slice(start,end);

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

const upload=await uploadChunkWithRetry(urlData.url,chunk);

const etag=upload.headers.get("ETag");

parts.push({
PartNumber:i+1,
ETag:etag
});

localStorage.setItem("uploadSession",JSON.stringify({
fileName:file.name,
uploadId,
key,
parts
}));

let percent=Math.floor((parts.length/totalChunks)*100);

document.getElementById("progressBar").style.width=percent+"%";
document.getElementById("progressText").innerText=percent+"%";

document.getElementById("statusText").innerText=
"Uploading chunk "+parts.length+" of "+totalChunks;

}

async function uploadChunkWithRetry(url,chunk,retries=3){

for(let attempt=1;attempt<=retries;attempt++){

try{

const response=await fetch(url,{
method:"PUT",
headers:{
"Content-Type":"application/octet-stream"
},
body:chunk
});

if(response.ok){
return response;
}

}catch(e){

console.log("Retry attempt",attempt);

}

await new Promise(r=>setTimeout(r,2000));

}

throw new Error("Chunk upload failed");

}

async function completeUpload(){

document.getElementById("statusText").innerText="Finalizing upload...";

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

localStorage.removeItem("uploadSession");

document.getElementById("loader").style.display="none";
document.getElementById("statusText").innerText="Upload completed";

alert("Upload Completed");

}

function cancelCurrentUpload(){
cancelUpload=true;
document.getElementById("loader").style.display="none";
}

function pauseUpload(){
paused=true;
}

function resumeUpload(){
paused=false;
startUpload();
}

function calculateSpeed(uploadedBytes){

let time=(Date.now()-startTime)/1000;

let speed=uploadedBytes/time;

let speedMB=(speed/(1024*1024)).toFixed(2);

document.getElementById("speedInfo").innerText=
"Upload Speed: "+speedMB+" MB/s";

}

function showChunkNumber(current,total){

document.getElementById("chunkInfo").innerText=
"Uploading Chunk "+current+" / "+total;

}

</script>

</body>
</html>
```
