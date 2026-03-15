<!-- <!DOCTYPE html>
<html>
<head>
<title>Large Video Upload</title>
<meta name="csrf-token" content="{{ csrf_token() }}"> -->
<!-- 
<style>

body{
    font-family: Arial, sans-serif;
    background:#f4f6f9;
    padding:40px;
}

h2{
    margin-bottom:20px;
}

#progressBar.done{
background: linear-gradient(45deg,#00c853,#64dd17);
animation:none;
}

/* Upload button */
button{
    padding:10px 20px;
    background:#007bff;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

button:hover{
    background:#0056b3;
}

/* Progress container */
#progressContainer{
    width:400px;
    height:25px;
    border-radius:20px;
    background:#e5e5e5;
    overflow:hidden;
    margin-top:20px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
}

/* Progress bar */
#progressBar{
    width:0%;
    height:100%;
    border-radius:20px;

    background: linear-gradient(
        45deg,
        #4facfe,
        #00f2fe
    );

    background-size: 40px 40px;

    animation: progressAnimation 2s linear infinite;

    transition: width 0.4s ease;
}

/* Animated stripes */
@keyframes progressAnimation{
    0%{
        background-position: 0 0;
    }
    100%{
        background-position: 40px 0;
    }
}

/* Percentage text */
#progressText{
    margin-top:10px;
    font-weight:bold;
    color:#333;
}

</style>
</head>

<body>

<h2>Upload Large Video</h2>

<div class="card">
<input type="file" class="form-control" id="videoInput" accept="video/*"><br><br>

<button onclick="uploadVideo()">Upload</button>

<div id="progressContainer">
    <div id="progressBar"></div>
</div>
</div>

<p id="progressText">0%</p>
<p id="resumeText" style="color:green;font-weight:bold;"> </p> -->
<!-- <script>

const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB per chunk

function uploadVideo() {

const file = document.getElementById('videoInput').files[0];

if (!file) return alert('Please select a video file.');

const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

let currentChunk = 0;

function sendChunk() {

const start = currentChunk * CHUNK_SIZE;
const end = Math.min(start + CHUNK_SIZE, file.size);
const chunk = file.slice(start, end);

const formData = new FormData();

formData.append('file', chunk);
formData.append('name', file.name);
formData.append('chunk', currentChunk);
formData.append('totalChunks', totalChunks);

fetch('/upload', {

method: 'POST',

headers: {
'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
},

body: formData

})
.then(res => res.json())

.then(data => {

if (data.error) {

alert('Upload failed at chunk ' + currentChunk + ': ' + data.error);

return;

}

const progressPercent = Math.floor(((currentChunk + 1) / totalChunks) * 100);

document.getElementById('progressBar').style.width = progressPercent + '%';

document.getElementById('progressText').innerText = progressPercent + '%';

currentChunk++;

// if (currentChunk < totalChunks) {

// sendChunk();

// } else {

// alert('Upload complete! Video is being processed.');

// }

if (currentChunk < totalChunks) {

sendChunk();

} else {

// stop animation
document.getElementById('progressBar').style.animation = 'none';

// fill bar completely
document.getElementById('progressBar').style.width = '100%';

// show done text
document.getElementById('progressText').innerText = 'Upload Done ✅';

alert('Upload complete! Video is being processed.');

}

})

.catch(err => {

console.error('Chunk upload error:', err);

alert('An error occurred during upload. Please retry.');

});

}

sendChunk();

}

</script> -->

<!-- <script>

const CHUNK_SIZE = 2 * 1024 * 1024;

// keep same upload session for resume
let uploadId = localStorage.getItem('upload_id');

if(!uploadId){
    uploadId = crypto.randomUUID();
    localStorage.setItem('upload_id', uploadId);
}

async function uploadVideo(){

const file = document.getElementById('videoInput').files[0];

if(!file){
alert("Please select a video");
return;
}

const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

// check already uploaded chunks (resume support)
const uploadedChunks = await getUploadedChunks(uploadId);

let currentChunk = uploadedChunks.length 
    ? Math.max(...uploadedChunks) + 1 
    : 0;

function sendChunk(retry = 0){

const start = currentChunk * CHUNK_SIZE;
const end = Math.min(start + CHUNK_SIZE, file.size);
const chunk = file.slice(start, end);

const formData = new FormData();

formData.append('file', chunk);
formData.append('name', file.name);
formData.append('chunk', currentChunk);
formData.append('totalChunks', totalChunks);
formData.append('upload_id', uploadId);

fetch('/upload',{
method:'POST',
headers:{
'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
},
body:formData
})
.then(res=>res.json())
.then(data=>{

if(data.error){
throw new Error(data.error);
}

// update progress
document.getElementById('progressBar').style.width = data.progress + '%';
document.getElementById('progressText').innerText = data.progress + '%';

currentChunk++;

if(currentChunk < totalChunks){

sendChunk();

}else{

// stop animation
document.getElementById('progressBar').style.animation = 'none';

// fill bar completely
document.getElementById('progressBar').style.width = '100%';

// show done text
document.getElementById('progressText').innerText = 'Upload Done ✅';
document.getElementById('progressBar').classList.add('done');

// clear session id so next upload starts fresh
localStorage.removeItem('upload_id');

alert("Upload completed successfully!");

}

})
.catch(err=>{

console.error("Chunk failed:", err);

if(retry < 3){

setTimeout(()=>{
sendChunk(retry + 1);
},2000);

}else{

alert("Upload failed after multiple retries.");

}

});

}

sendChunk();

} -->

<!-- // async function getUploadedChunks(uploadId){

// try{

// const res = await fetch('/upload-status?upload_id='+uploadId);

// const data = await res.json();

// return data.uploadedChunks || [];

// }catch(e){

// console.log("Status check failed");

// return [];

// } -->

<!-- async function getUploadedChunks(uploadId){

const res = await fetch('/upload-status?upload_id='+uploadId);

const data = await res.json();

if(data.uploadedChunks && data.uploadedChunks.length > 0){

document.getElementById('resumeText').innerText =
"Resuming upload from chunk " + (Math.max(...data.uploadedChunks) + 1);

}

return data.uploadedChunks || [];

}

}

</script> -->



<!-- </body>
</html> -->

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
<button onclick="cancelCurrentUpload()" style="margin-top:10px;background:#dc3545;">
Cancel Upload
</button>
</div>
<!-- <script>

const chunkSize = 20 * 1024 * 1024; // 20MB chunks (faster)
const parallelUploads = 5; // 5 chunks ek saath upload honge

async function startUpload(){

const file = document.getElementById("fileInput").files[0]

if(!file){
alert("Select file")
return
}

/* INIT MULTIPART */

const initRes = await fetch("/upload/init",{
method:"POST",
headers:{
"Content-Type":"application/json",
"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content
},
body:JSON.stringify({
file_name:file.name
})
})

const initData = await initRes.json()

const uploadId = initData.uploadId
const key = initData.key

const totalChunks = Math.ceil(file.size / chunkSize)

let parts = []
let uploaded = 0


async function uploadChunk(i){

const start = i * chunkSize
const end = Math.min(file.size,start+chunkSize)

const chunk = file.slice(start,end)

/* GET PRESIGNED URL */

const urlRes = await fetch("/s3-presigned-url",{
method:"POST",
headers:{
"Content-Type":"application/json",
"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content
},
body:JSON.stringify({
uploadId:uploadId,
key:key,
partNumber:i+1
})
})

const urlData = await urlRes.json()

/* UPLOAD PART */

const upload = await fetch(urlData.url,{
method:"PUT",
body:chunk
})

const etag = upload.headers.get("ETag")

parts.push({
PartNumber:i+1,
ETag:etag
})

uploaded++

let percent = Math.floor((uploaded / totalChunks) * 100)

document.getElementById("progressBar").style.width = percent+"%"
document.getElementById("progressText").innerText = percent+"%"

}


/* PARALLEL UPLOAD SYSTEM */

let index = 0

async function worker(){

while(index < totalChunks){

let i = index++
await uploadChunk(i)

}

}

let workers = []

for(let i=0;i<parallelUploads;i++){

workers.push(worker())

}

await Promise.all(workers)


/* COMPLETE MULTIPART */

await fetch("/upload/complete",{
method:"POST",
headers:{
"Content-Type":"application/json",
"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content
},
body:JSON.stringify({
uploadId:uploadId,
key:key,
parts:parts
})
})

document.getElementById("progressBar").style.width = "100%"
document.getElementById("progressText").innerText = "100%"

alert("Upload completed!")

}

</script> -->

<script>

const chunkSize = 5 * 1024 * 1024; // 5MB
let cancelUpload = false;

async function startUpload(){

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

</script>
</body>
</html>
