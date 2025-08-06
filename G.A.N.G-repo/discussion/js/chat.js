// chat.js (modular & improved)

const CURRENT_USER_ID = parseInt(document.getElementById('currentUserId').value);
const inputField = document.getElementById('chatInput');
const sendButton = document.getElementById('sendButton');
const micButton = document.getElementById('micButton');
const stopButton = document.getElementById('stopButton');
const cancelButton = document.getElementById('cancelButton');
const timerDisplay = document.getElementById('timer');
const messagesContainer = document.getElementById('messages');

let recording = false;
let recorder;
let audioChunks = [];
let timerInterval;
let seconds = 0;

// Init
initChatUI();

function initChatUI() {
  toggleSendMic();
  inputField.addEventListener('input', toggleSendMic);
  sendButton.addEventListener('click', handleSendText);
  micButton.addEventListener('click', startRecording);
  stopButton.addEventListener('click', stopRecording);
  cancelButton.addEventListener('click', cancelRecording);
}

function handleSendText() {
  const message = inputField.value.trim();
  if (!message) return;

  sendMessage(message);
  inputField.value = '';
  toggleSendMic();
}

function toggleSendMic() {
  const hasText = inputField.value.trim().length > 0;

  sendButton.style.display = hasText || recording ? 'inline-block' : 'none';
  micButton.style.display = hasText || recording ? 'none' : 'inline-block';
}

// Timer
function startTimer() {
  seconds = 0;
  timerDisplay.textContent = formatTime(seconds);
  timerDisplay.style.display = 'inline';
  timerInterval = setInterval(() => {
    seconds++;
    timerDisplay.textContent = formatTime(seconds);
  }, 1000);
}

function stopTimer() {
  clearInterval(timerInterval);
  timerDisplay.style.display = 'none';
}

function formatTime(sec) {
  const mins = Math.floor(sec / 60).toString().padStart(2, '0');
  const secs = (sec % 60).toString().padStart(2, '0');
  return `${mins}:${secs}`;
}

// Recording
async function startRecording() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    recorder = new MediaRecorder(stream);
    audioChunks = [];

    recorder.ondataavailable = e => audioChunks.push(e.data);

    recorder.onstop = () => {
      const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
      sendAudio(audioBlob);
    };

    recorder.start();
    recording = true;
    startTimer();
    toggleSendMic();
    updateRecordingUI(true);
  } catch (err) {
    console.error('Microphone error:', err);
    alert('Microphone access is required to record audio.');
  }
}

function stopRecording() {
  if (recorder && recorder.state === 'recording') {
    recorder.stop();
    recording = false;
    stopTimer();
    updateRecordingUI(false);
    toggleSendMic();
  }
}

function cancelRecording() {
  if (recorder && recorder.state === 'recording') {
    recorder.stop();
  }
  recording = false;
  stopTimer();
  updateRecordingUI(false);
  toggleSendMic();
  console.log('Recording cancelled');
}

function updateRecordingUI(isRecording) {
  stopButton.style.display = isRecording ? 'inline' : 'none';
  cancelButton.style.display = isRecording ? 'inline' : 'none';
  micButton.style.display = isRecording ? 'none' : 'inline';
}

// Message sending
function sendMessage(message) {
  const msg = createMessageObj('text', message);
  renderMessage(msg);
  // TODO: Send to backend via fetch
}

function sendAudio(blob) {
  const audioUrl = URL.createObjectURL(blob);
  const msg = createMessageObj('audio', audioUrl);
  renderMessage(msg);
  // TODO: Upload blob to backend
}

function createMessageObj(type, content) {
  return {
    id: Date.now(),
    sender_id: CURRENT_USER_ID,
    content,
    type,
    timestamp: new Date().toISOString()
  };
}

// UI Rendering
function renderMessage(msg) {
  const wrapper = document.createElement('div');
  wrapper.className = 'message ' + (msg.sender_id === CURRENT_USER_ID ? 'sent' : 'received');

  const content = document.createElement('div');
  content.className = 'bubble';

  if (msg.type === 'text') {
    content.textContent = msg.content;
  } else if (msg.type === 'audio') {
    const audio = document.createElement('audio');
    audio.src = msg.content;
    audio.controls = true;
    content.appendChild(audio);
  }

  wrapper.appendChild(content);

  if (msg.sender_id === CURRENT_USER_ID) {
    const deleteBtn = document.createElement('span');
    deleteBtn.textContent = '🗑️';
    deleteBtn.className = 'delete-btn';
    deleteBtn.onclick = () => {
      wrapper.remove();
      // TODO: backend delete call
    };
    wrapper.appendChild(deleteBtn);
  }

  messagesContainer.appendChild(wrapper);
  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}
