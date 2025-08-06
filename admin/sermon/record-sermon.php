<?php
session_start();
$speaker_name = isset($_SESSION['second_name']) ? $_SESSION['second_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Record Sermon | G.A.N.G Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

 <link href="/G.A.N.G/includes/css/main.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/header.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/footer.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/layout.css" rel="stylesheet">
    <link href="/G.A.N.G/admin/css/record_sermon.css" rel="stylesheet">
</head>
<body>

<div id="header"></div>
<?php include dirname(__DIR__, 2) . '/includes/welcome_section.php'; ?>


<div class="container-fluid py-4">
  <div class="recorder-container fade-in">
    <!-- Header -->
    <div class="text-center mb-4">
      <h2 class="mb-2"><i class="bi bi-mic-fill me-2"></i>Sermon Recording Studio</h2>
      <p class="text-muted">Professional sermon recording with automatic chunking and concurrent file management</p>
    </div>

    <!-- Status Bar -->
    <div id="statusBar" class="status-bar">
      <i class="bi bi-info-circle"></i>
      <span id="statusText">Ready to record</span>
    </div>

    <!-- Session Indicator -->
    <div id="sessionIndicator" class="session-indicator">
      <div class="session-info">
        <i class="bi bi-folder2-open"></i>
        <div>
          <span>Session Active: </span>
          <strong id="sessionFolder">--</strong>
        </div>
      </div>
      <div class="session-stats">
        <span id="sessionStats">Audio: 0 | Slides: 0</span>
      </div>
    </div>

    <!-- Recording Status -->
    <div id="recordingStatus" class="recording-status">
      <div class="d-flex align-items-center">
        <i class="bi bi-record-circle me-3"></i>
        <div>
          <strong>Recording in Progress</strong>
          <div class="small">Auto-saving every 25 minutes</div>
        </div>
      </div>
    </div>

    <!-- Sermon Details -->
    <div class="row mb-4">
      <div class="col-md-8">
        <label for="sermon-title" class="form-label fw-bold">
          Sermon Title <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control form-control-lg" id="sermon-title" 
               placeholder="Enter sermon title (e.g., The Power of Baptism)" 
               required minlength="3" />
        <div class="form-text">Minimum 3 characters required</div>
      </div>
      <div class="col-md-4">
        <label for="speaker-name" class="form-label fw-bold">Speaker</label>
        <input type="text" class="form-control form-control-lg" id="speaker-name" 
               placeholder="Pastor/Speaker name" />
        <div class="form-text">Will use "Unknown Speaker" if empty</div>
      </div>
    </div>

    <!-- Waveform Visualization -->
    <div id="waveform"></div>

    <!-- Control Buttons -->
    <div class="control-buttons">
      <button id="micButton" class="btn-record mic" title="Start Recording">
        <i class="bi bi-mic-fill"></i>
      </button>
      
      <div class="timer-display" id="timer">00:00:00</div>
      
      <button id="sendButton" class="btn-send" title="Stop & Save Recording">
        <i class="bi bi-stop-fill"></i>
      </button>
    </div>

    <!-- Chunk Indicator -->
    <div id="chunkIndicator" class="chunk-indicator">
      <i class="bi bi-archive me-2"></i>
      <span id="chunkText">Chunk 1 saved automatically</span>
    </div>

    <!-- Recording Statistics -->
    <div class="recording-stats" id="recordingStats" style="display: none;">
      <div class="stat-card">
        <div class="stat-value" id="totalDuration">00:00:00</div>
        <div class="stat-label">Total Duration</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" id="chunkCount">0</div>
        <div class="stat-label">Chunks Saved</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" id="fileSize">0 MB</div>
        <div class="stat-label">Est. File Size</div>
      </div>
    </div>

    <!-- Session Actions -->
    <div class="session-actions">
      <button id="saveCompleteBtn" class="btn-session-action btn-save-complete" title="Save complete session">
        <i class="bi bi-cloud-check"></i>
        Save Complete Session
      </button>
      <button id="resetSessionBtn" class="btn-session-action btn-reset-session" title="Reset current session">
        <i class="bi bi-arrow-clockwise"></i>
        Reset Session
      </button>
    </div>

    <!-- Slides Upload Section -->
    <div class="upload-section" id="uploadSection">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-file-earmark-slides me-2"></i>Upload Sermon Slides (Optional)</h5>
        <button id="saveSlidesBtn" class="btn-save-slides" title="Save uploaded slides">
          <i class="bi bi-cloud-upload me-2"></i>Save Slides
        </button>
      </div>
      <div class="mb-3">
        <input type="file" class="form-control" id="slidesUpload" 
               accept=".pdf,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp" multiple />
        <div class="form-text">Supported formats: PDF, PowerPoint (.ppt, .pptx), Images (.jpg, .png, .gif, .webp)</div>
      </div>
      
      <div class="file-list" id="fileList"></div>
      
      <div id="uploadFeedback" class="mt-3"></div>
    </div>
  </div>
</div>

<!-- Enhanced Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Session Complete!</h5>
      </div>
      <div class="modal-body">
        <p>Your sermon session has been successfully completed and saved.</p>
        <div id="sermonDetails"></div>
        
        <!-- Session Summary -->
        <div class="row mt-4">
          <div class="col-md-4">
            <div class="card border-0 bg-light">
              <div class="card-body text-center">
                <i class="bi bi-mic-fill text-primary mb-2" style="font-size: 2rem;"></i>
                <h6>Audio</h6>
                <p class="mb-0" id="summaryAudio">0 chunks</p>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 bg-light">
              <div class="card-body text-center">
                <i class="bi bi-file-earmark-slides text-info mb-2" style="font-size: 2rem;"></i>
                <h6>Slides</h6>
                <p class="mb-0" id="summarySlides">0 files</p>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 bg-light">
              <div class="card-body text-center">
                <i class="bi bi-hdd text-success mb-2" style="font-size: 2rem;"></i>
                <h6>Total Size</h6>
                <p class="mb-0" id="summarySize">0 MB</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-success" onclick="location.reload()">Record Another</button>
      </div>
    </div>
  </div>
</div>

<div id="footer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="/G.A.N.G/includes/js/include.js"></script>

<script>

  
// Get speaker name from PHP session
const SPEAKER_NAME = '<?php echo addslashes($speaker_name); ?>';

$(function() {
  // DOM Elements
  const micButton = $('#micButton');
  const sendButton = $('#sendButton');
  const timerDisplay = $('#timer');
  const sermonTitleInput = $('#sermon-title');
  const speakerNameInput = $('#speaker-name');
  const slidesUpload = $('#slidesUpload');
  const uploadFeedback = $('#uploadFeedback');
  const fileList = $('#fileList');
  const statusBar = $('#statusBar');
  const statusText = $('#statusText');
  const recordingStatus = $('#recordingStatus');
  const chunkIndicator = $('#chunkIndicator');
  const chunkText = $('#chunkText');
  const recordingStats = $('#recordingStats');
  const waveformElement = $('#waveform');
  const saveSlidesBtn = $('#saveSlidesBtn');
  const sessionIndicator = $('#sessionIndicator');
  const sessionFolder = $('#sessionFolder');
  const sessionStats = $('#sessionStats');
  const saveCompleteBtn = $('#saveCompleteBtn');
  const resetSessionBtn = $('#resetSessionBtn');

  // Recording State
  let mediaRecorder;
  let audioChunks = [];
  let chunkIndex = 0;
  let recordingStartTime = null;
  let recordingInterval = null;
  let chunkInterval = null;
  let folderName = '';
  let isRecording = false;
  let stream;
  let audioContext;
  let analyser;
  let dataArray;
  let animationId;
  let totalChunks = 0;
  let estimatedSize = 0;
  let uploadedSlides = [];
  let sessionActive = false;
  let statusTimeouts = [];
  let currentSermonTitle = '';
  let currentSpeakerName = '';
  
  // NEW: Seamless recording variables
  let sessionChunks = []; // Store chunks temporarily during session
  let currentRecordingSession = null; // Track active recording session
  let backgroundUploadQueue = []; // Queue for background uploads
  let isProcessingUpload = false; // Prevent upload conflicts

  // Constants - 1-minute chunks for seamless operation
  const CHUNK_DURATION = 1 * 60 * 1000; // 1 minute in milliseconds
  const CHUNK_DURATION_SECONDS = 1 * 60; // 1 minute in seconds
  const SUCCESS_MESSAGE_TIMEOUT = 5000; // 5 seconds
  const ERROR_MESSAGE_TIMEOUT = 8000; // 8 seconds

  // Initialize speaker name
  if (SPEAKER_NAME) {
    speakerNameInput.val(SPEAKER_NAME);
    currentSpeakerName = SPEAKER_NAME;
  }

  // Utility Functions
  function formatTime(seconds) {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return [hrs, mins, secs].map(n => n.toString().padStart(2, '0')).join(':');
  }

  function generateFolderName(title, speaker) {
    const sanitizedTitle = title.replace(/[^a-zA-Z0-9_-]/g, '_').slice(0, 30);
    const sanitizedSpeaker = speaker.replace(/[^a-zA-Z0-9_-]/g, '_').slice(0, 20);
    const timestamp = new Date().toISOString().replace(/[-:.TZ]/g, '').slice(0, 14);
    return `${sanitizedTitle}_${sanitizedSpeaker}_${timestamp}`;
  }

  function clearStatusTimeouts() {
    statusTimeouts.forEach(timeout => clearTimeout(timeout));
    statusTimeouts = [];
  }

  function updateStatus(message, type = 'info', timeout = null) {
    clearStatusTimeouts();
    
    statusText.text(message);
    statusBar.removeClass('show').addClass('show');
    
    if (type === 'success') {
      statusBar.css('background', 'linear-gradient(90deg, #28a745, #20c997)');
      timeout = timeout || SUCCESS_MESSAGE_TIMEOUT;
    } else if (type === 'error') {
      statusBar.css('background', 'linear-gradient(90deg, #dc3545, #fd7e14)');
      timeout = timeout || ERROR_MESSAGE_TIMEOUT;
    } else {
      statusBar.css('background', 'linear-gradient(90deg, #667eea, #764ba2)');
    }

    // Auto-hide status after timeout
    if (timeout) {
      const timeoutId = setTimeout(() => {
        statusBar.removeClass('show');
      }, timeout);
      statusTimeouts.push(timeoutId);
    }
  }

  function updateTimer() {
    const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
    timerDisplay.text(formatTime(elapsed));
    
    // Update stats
    $('#totalDuration').text(formatTime(elapsed));
    $('#chunkCount').text(sessionChunks.length);
    estimatedSize = (elapsed * 0.1); // Rough estimate: 0.1MB per second
    $('#fileSize').text(estimatedSize.toFixed(1) + ' MB');
  }

  function showChunkIndicator(chunkNum) {
    chunkText.text(`Chunk ${chunkNum} processed seamlessly (${formatTime(chunkNum * CHUNK_DURATION_SECONDS)})`);
    chunkIndicator.addClass('show');
    setTimeout(() => chunkIndicator.removeClass('show'), 3000); // Shorter display time
  }

  function updateSessionIndicator() {
    if (sessionActive && folderName) {
      sessionFolder.text(folderName);
      sessionStats.text(`Chunks: ${sessionChunks.length} | Slides: ${uploadedSlides.length}`);
      sessionIndicator.addClass('active');
      saveCompleteBtn.addClass('show');
      resetSessionBtn.addClass('show');
    } else {
      sessionIndicator.removeClass('active');
      saveCompleteBtn.removeClass('show');
      resetSessionBtn.removeClass('show');
    }
  }

  // Enhanced Form Validation
  function validateSessionData() {
    const title = sermonTitleInput.val().trim();
    const speaker = speakerNameInput.val().trim();

    // Validate title (required)
    if (!title) {
      updateStatus('Please enter a sermon title before recording.', 'error');
      sermonTitleInput.focus();
      return false;
    }

    // Validate title length
    if (title.length < 3) {
      updateStatus('Sermon title must be at least 3 characters long.', 'error');
      sermonTitleInput.focus();
      return false;
    }

    // Validate speaker (use default if empty)
    const finalSpeaker = speaker || 'Unknown Speaker';

    // Cache validated values
    currentSermonTitle = title;
    currentSpeakerName = finalSpeaker;

    // Update speaker field if it was empty
    if (!speaker) {
      speakerNameInput.val(finalSpeaker);
    }

    return true;
  }

  // Form Reset Functions
  function resetForm() {
    // Reset form inputs
    sermonTitleInput.val('');
    currentSermonTitle = '';
    
    if (SPEAKER_NAME) {
      speakerNameInput.val(SPEAKER_NAME);
      currentSpeakerName = SPEAKER_NAME;
    } else {
      speakerNameInput.val('');
      currentSpeakerName = '';
    }
    
    // Reset file upload
    slidesUpload.val('');
    fileList.empty();
    uploadFeedback.empty();
    saveSlidesBtn.removeClass('show');
    
    // Reset session state
    sessionActive = false;
    uploadedSlides = [];
    folderName = '';
    totalChunks = 0;
    
    // Reset seamless recording state
    sessionChunks = [];
    currentRecordingSession = null;
    backgroundUploadQueue = [];
    isProcessingUpload = false;
    
    // Update session indicator
    updateSessionIndicator();
    
    // Clear any status messages
    clearStatusTimeouts();
    statusBar.removeClass('show');
    
    updateStatus('Ready to start new seamless recording', 'info', SUCCESS_MESSAGE_TIMEOUT);
  }

  function resetRecordingState() {
    isRecording = false;
    
    // Clear intervals
    if (recordingInterval) clearInterval(recordingInterval);
    if (chunkInterval) clearInterval(chunkInterval);

    // Reset UI
    micButton.removeClass('stop').addClass('mic').find('i').removeClass('bi-stop-fill').addClass('bi-mic-fill');
    sendButton.removeClass('show');
    recordingStatus.removeClass('active');
    waveformElement.removeClass('recording');
    recordingStats.hide();
    timerDisplay.text('00:00:00');

    // Stop media
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
    }
    stopVisualization();
  }

  // Enhanced Session Management - SEAMLESS VERSION
  function initializeRecordingSession() {
    // Validate form data first
    if (!validateSessionData()) {
      return false;
    }

    if (!sessionActive) {
      folderName = generateFolderName(currentSermonTitle, currentSpeakerName);
      sessionActive = true;
      currentRecordingSession = {
        id: Date.now(),
        title: currentSermonTitle,
        speaker: currentSpeakerName,
        folderName: folderName,
        startTime: new Date(),
        chunks: [],
        totalDuration: 0,
        isComplete: false
      };
      
      updateSessionIndicator();
      updateStatus('Session initialized - Ready for seamless recording', 'success', 3000);
    }
    
    return true;
  }

  // Audio Visualization (keeping existing)
  function setupAudioVisualization() {
    if (!stream) return;

    audioContext = new (window.AudioContext || window.webkitAudioContext)();
    analyser = audioContext.createAnalyser();
    const source = audioContext.createMediaStreamSource(stream);
    source.connect(analyser);

    analyser.fftSize = 256;
    const bufferLength = analyser.frequencyBinCount;
    dataArray = new Uint8Array(bufferLength);

    const canvas = $('<canvas>').attr({
      width: waveformElement.width(),
      height: waveformElement.height()
    }).css({
      width: '100%',
      height: '100%',
      borderRadius: '15px'
    });

    waveformElement.empty().append(canvas);
    const canvasCtx = canvas[0].getContext('2d');

    function draw() {
      if (!isRecording) return;
      
      animationId = requestAnimationFrame(draw);
      analyser.getByteFrequencyData(dataArray);

      canvasCtx.fillStyle = 'rgba(30, 60, 114, 0.1)';
      canvasCtx.fillRect(0, 0, canvas[0].width, canvas[0].height);

      const barWidth = (canvas[0].width / bufferLength) * 2.5;
      let barHeight;
      let x = 0;

      for (let i = 0; i < bufferLength; i++) {
        barHeight = (dataArray[i] / 255) * canvas[0].height * 0.8;
        
        const gradient = canvasCtx.createLinearGradient(0, canvas[0].height - barHeight, 0, canvas[0].height);
        gradient.addColorStop(0, '#667eea');
        gradient.addColorStop(1, '#764ba2');
        
        canvasCtx.fillStyle = gradient;
        canvasCtx.fillRect(x, canvas[0].height - barHeight, barWidth, barHeight);
        
        x += barWidth + 1;
      }
    }

    draw();
  }

  function stopVisualization() {
    if (animationId) {
      cancelAnimationFrame(animationId);
      animationId = null;
    }
    if (audioContext && audioContext.state !== 'closed') {
      audioContext.close().catch(console.error);
      audioContext = null;
    }
    waveformElement.empty();
  }

  // NEW: Seamless MediaRecorder Management
  async function createNewRecorder() {
    return new Promise((resolve, reject) => {
      try {
        mediaRecorder = new MediaRecorder(stream, {
          mimeType: 'audio/webm;codecs=opus',
          audioBitsPerSecond: 128000
        });

        audioChunks = []; // Reset chunks for new recorder

        mediaRecorder.ondataavailable = function(event) {
          if (event.data.size > 0) {
            audioChunks.push(event.data);
          }
        };

        mediaRecorder.onstop = function() {
          console.log('Recorder stopped, chunks collected:', audioChunks.length);
          resolve();
        };

        mediaRecorder.onerror = function(error) {
          console.error('MediaRecorder error:', error);
          reject(error);
        };

        // Start recording immediately
        mediaRecorder.start();
        console.log('New MediaRecorder started for chunk', chunkIndex);
        resolve();

      } catch (error) {
        reject(error);
      }
    });
  }

  // NEW: Process chunks seamlessly without user interruption
  async function processSeamlessChunk() {
    if (!isRecording || !mediaRecorder || mediaRecorder.state !== 'recording') {
      return;
    }

    console.log('Processing seamless chunk', chunkIndex);

    try {
      // Stop current recorder to get the chunk
      mediaRecorder.stop();

      // Wait for onstop to complete and get chunks
      await new Promise(resolve => setTimeout(resolve, 100));

      if (audioChunks.length > 0) {
        // Store chunk in session (not uploaded yet)
        const chunkBlob = new Blob(audioChunks, { type: 'audio/webm' });
        const chunkData = {
          index: chunkIndex,
          blob: chunkBlob,
          size: chunkBlob.size,
          timestamp: new Date(),
          duration: CHUNK_DURATION_SECONDS
        };

        sessionChunks.push(chunkData);
        currentRecordingSession.chunks.push(chunkData);

        totalChunks++;
        showChunkIndicator(totalChunks);
        updateSessionIndicator();

        console.log(`Chunk ${chunkIndex} stored in session (${chunkBlob.size} bytes)`);
        chunkIndex++;
      }

      // Immediately start new recorder for seamless continuation
      if (isRecording) {
        await createNewRecorder();
        console.log('Seamless recording continued with new recorder');
      }

    } catch (error) {
      console.error('Error processing seamless chunk:', error);
      updateStatus('Chunk processing error (recording continues)', 'error', 3000);
      
      // Try to restart recording if it failed
      if (isRecording) {
        try {
          await createNewRecorder();
        } catch (restartError) {
          console.error('Failed to restart recording:', restartError);
          updateStatus('Recording restart failed', 'error');
        }
      }
    }
  }

  // Enhanced Recording Functions - SEAMLESS VERSION
  async function startRecording() {
    if (!initializeRecordingSession()) return;

    try {
      // Re-validate data right before recording
      if (!currentSermonTitle || !currentSpeakerName) {
        updateStatus('Session data validation failed. Please try again.', 'error');
        return;
      }

      // Setup recording state
      chunkIndex = 0;
      totalChunks = 0;
      audioChunks = [];
      sessionChunks = [];
      isRecording = true;

      // UI Updates
      micButton.removeClass('mic').addClass('stop').find('i').removeClass('bi-mic-fill').addClass('bi-stop-fill');
      sendButton.addClass('show');
      recordingStatus.addClass('active');
      waveformElement.addClass('recording');
      recordingStats.show();
      
      recordingStartTime = Date.now();
      updateTimer();
      recordingInterval = setInterval(updateTimer, 1000);

      // Setup media recording
      stream = await navigator.mediaDevices.getUserMedia({ 
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          sampleRate: 44100
        }
      });

      setupAudioVisualization();

      // Create initial MediaRecorder
      await createNewRecorder();

      updateStatus('Recording started - Seamless 1-minute chunking enabled', 'success');
      updateSessionIndicator();

      // Setup seamless chunking every 1 minute
      chunkInterval = setInterval(async () => {
        if (!isRecording) return;
        
        console.log('Seamless chunk processing at', new Date().toLocaleTimeString());
        await processSeamlessChunk();
      }, CHUNK_DURATION);

    } catch (error) {
      console.error('Error starting seamless recording:', error);
      updateStatus('Failed to start recording: ' + error.message, 'error');
      resetRecordingState();
    }
  }

  async function stopRecording() {
    if (!isRecording) return;

    console.log('Stopping seamless recording...');
    isRecording = false;
    
    // Clear intervals
    if (recordingInterval) clearInterval(recordingInterval);
    if (chunkInterval) clearInterval(chunkInterval);

    // Stop current recorder and get final chunk
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      mediaRecorder.stop();
      await new Promise(resolve => setTimeout(resolve, 200));

      // Process final chunk if it exists
      if (audioChunks.length > 0) {
        const finalChunkBlob = new Blob(audioChunks, { type: 'audio/webm' });
        const finalChunkData = {
          index: chunkIndex,
          blob: finalChunkBlob,
          size: finalChunkBlob.size,
          timestamp: new Date(),
          duration: Math.floor((Date.now() - recordingStartTime) / 1000) % CHUNK_DURATION_SECONDS,
          isFinal: true
        };

        sessionChunks.push(finalChunkData);
        currentRecordingSession.chunks.push(finalChunkData);
        totalChunks++;
      }
    }

    // Stop all tracks
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
    }

    stopVisualization();
    updateStatus('Processing complete recording session...', 'info');

    // Now save everything to database
    await saveCompleteSession();
  }

  // NEW: Save complete session with all chunks at once
  async function saveCompleteSession() {
    updateStatus('Saving complete session to database...', 'info');

    try {
      // Mark session as complete
      currentRecordingSession.isComplete = true;
      currentRecordingSession.endTime = new Date();
      currentRecordingSession.totalDuration = Math.floor((Date.now() - recordingStartTime) / 1000);

      // Upload all chunks in sequence
      const uploadResults = [];
      
      for (let i = 0; i < sessionChunks.length; i++) {
        const chunk = sessionChunks[i];
        console.log(`Uploading chunk ${i + 1}/${sessionChunks.length}`);
        
        updateStatus(`Uploading chunk ${i + 1} of ${sessionChunks.length}...`, 'info');
        
        const result = await uploadSessionChunk(chunk, i === sessionChunks.length - 1);
        uploadResults.push(result);
      }

      // Finalize sermon in database
      await finalizeCompleteSession();

      // Process any pending slides
      if (uploadedSlides.length > 0) {
        await processPendingSlides();
      }

      updateStatus('Session saved successfully!', 'success');
      showCompletionModal();
      
      // Reset everything after a delay
      setTimeout(() => {
        resetRecordingState();
        resetForm();
      }, 1000);

    } catch (error) {
      console.error('Session save error:', error);
      updateStatus('Error saving session: ' + error.message, 'error');
    }
  }

  // NEW: Upload individual chunk from session
  async function uploadSessionChunk(chunkData, isFinal = false) {
    const formData = new FormData();
    
    formData.append('title', currentSermonTitle);
    formData.append('speaker', currentSpeakerName);
    formData.append('folder_name', folderName);
    formData.append('chunk_index', chunkData.index);
    formData.append('is_final', isFinal ? '1' : '0');
    formData.append('audio_chunk', chunkData.blob, `chunk_${chunkData.index.toString().padStart(3, '0')}.webm`);

    console.log('Uploading session chunk:', {
      title: currentSermonTitle,
      speaker: currentSpeakerName,
      folder: folderName,
      index: chunkData.index,
      isFinal: isFinal,
      size: chunkData.size
    });

    const response = await fetch('/G.A.N.G/admin/sermon/upload_chunk.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.message || 'Chunk upload failed');
    }

    return result;
  }

  // NEW: Finalize complete session
  async function finalizeCompleteSession() {
    const formData = new FormData();
    formData.append('title', currentSermonTitle);
    formData.append('speaker', currentSpeakerName);
    formData.append('folder_name', folderName);
    formData.append('total_chunks', sessionChunks.length);
    formData.append('total_duration', currentRecordingSession.totalDuration);

    console.log('Finalizing complete session:', {
      title: currentSermonTitle,
      speaker: currentSpeakerName,
      folder: folderName,
      chunks: sessionChunks.length,
      duration: currentRecordingSession.totalDuration
    });

    const response = await fetch('/G.A.N.G/admin/sermon/save_sermon.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.message || 'Failed to finalize session');
    }

    return result;
  }

  async function processPendingSlides() {
    if (uploadedSlides.length === 0) return;

    const formData = new FormData();
    formData.append('folder_name', folderName);
    
    uploadedSlides.forEach(slide => {
      formData.append('slides[]', slide);
    });

    const response = await fetch('/G.A.N.G/admin/sermon/upload_slides.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.message || 'Failed to process slides');
    }

    return result;
  }

  function showCompletionModal() {
    const details = `
      <div class="alert alert-success">
        <h6><strong>Title:</strong> ${currentSermonTitle}</h6>
        <p><strong>Speaker:</strong> ${currentSpeakerName}</p>
        <p><strong>Duration:</strong> ${$('#totalDuration').text()}</p>
        <p><strong>Audio Chunks:</strong> ${sessionChunks.length}</p>
        <p><strong>Slides:</strong> ${uploadedSlides.length}</p>
        <p><strong>Folder:</strong> ${folderName}</p>
      </div>
    `;
    
    $('#sermonDetails').html(details);
    $('#summaryAudio').text(`${sessionChunks.length} chunks`);
    $('#summarySlides').text(`${uploadedSlides.length} files`);
    $('#summarySize').text(estimatedSize.toFixed(1) + ' MB');
    
    new bootstrap.Modal($('#successModal')[0]).show();
  }

  // Enhanced File Upload Functions
  function handleFileSelect(files) {
    fileList.empty();
    uploadedSlides = Array.from(files);
    
    Array.from(files).forEach((file, index) => {
      const fileItem = $(`
        <div class="file-item">
          <div class="d-flex align-items-center">
            <i class="bi bi-file-earmark me-2"></i>
            <span>${file.name}</span>
            <small class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
          </div>
          <button class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
            <i class="bi bi-x"></i>
          </button>
        </div>
      `);
      fileList.append(fileItem);
    });

    if (files.length > 0) {
      saveSlidesBtn.addClass('show');
    } else {
      saveSlidesBtn.removeClass('show');
    }
    
    updateSessionIndicator();
  }

  async function uploadSlides() {
    const files = slidesUpload[0].files;
    if (!files.length) {
      updateStatus('Please select slides to upload.', 'error');
      return;
    }

    if (!initializeRecordingSession()) return;

    uploadFeedback.html('<div class="text-info"><i class="bi bi-upload me-2"></i>Uploading slides...</div>');

    const formData = new FormData();
    Array.from(files).forEach(file => {
      formData.append('slides[]', file);
    });
    formData.append('folder_name', folderName);

    try {
      const response = await fetch('/G.A.N.G/admin/sermon/upload_slides.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      
      if (result.success) {
        uploadFeedback.html('<div class="text-success"><i class="bi bi-check-circle me-2"></i>Slides uploaded successfully!</div>');
        saveSlidesBtn.removeClass('show');
        updateStatus(`${files.length} slides uploaded successfully!`, 'success');
        uploadedSlides = [];
        updateSessionIndicator();
        
        setTimeout(() => {
          uploadFeedback.empty();
        }, SUCCESS_MESSAGE_TIMEOUT);
      } else {
        throw new Error(result.message || 'Upload failed');
      }
    } catch (error) {
      uploadFeedback.html(`<div class="text-danger"><i class="bi bi-exclamation-circle me-2"></i>Upload failed: ${error.message}</div>`);
      updateStatus('Slide upload failed: ' + error.message, 'error');
    }
  }

  // Session Action Functions
  async function saveCompleteSessionAction() {
    if (!sessionActive || !folderName) {
      updateStatus('No active session to save.', 'error');
      return;
    }

    updateStatus('Saving complete session...', 'info');

    try {
      const formData = new FormData();
      formData.append('folder_name', folderName);
      formData.append('session_type', 'complete');

      const response = await fetch('/G.A.N.G/admin/sermon/save_session_complete.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.success) {
        updateStatus('Session saved successfully!', 'success');
        showCompletionModal();
        setTimeout(() => {
          resetForm();
          resetRecordingState();
        }, 2000);
      } else {
        throw new Error(result.message || 'Failed to save session');
      }
    } catch (error) {
      console.error('Save session error:', error);
      updateStatus('Error saving session: ' + error.message, 'error');
    }
  }

  function resetSession() {
    if (isRecording) {
      updateStatus('Cannot reset session while recording. Stop recording first.', 'error');
      return;
    }

    if (confirm('Are you sure you want to reset the current session? All unsaved progress will be lost.')) {
      resetForm();
      resetRecordingState();
      updateStatus('Session reset successfully.', 'success');
    }
  }

  // Event Listeners
  micButton.on('click', function() {
    if (!isRecording) {
      startRecording();
    } else {
      updateStatus('Click the stop button to finish recording', 'info');
    }
  });

  sendButton.on('click', function() {
    if (isRecording) {
      stopRecording();
    }
  });

  saveSlidesBtn.on('click', function() {
    uploadSlides();
  });

  saveCompleteBtn.on('click', function() {
    saveCompleteSessionAction();
  });

  resetSessionBtn.on('click', function() {
    resetSession();
  });

  slidesUpload.on('change', function() {
    const files = this.files;
    if (files.length) {
      handleFileSelect(files);
    }
  });

  // Enhanced form validation on input change
  sermonTitleInput.on('input', function() {
    const title = $(this).val().trim();
    if (title.length >= 3) {
      $(this).removeClass('is-invalid').addClass('is-valid');
    } else if (title.length > 0) {
      $(this).removeClass('is-valid').addClass('is-invalid');
    } else {
      $(this).removeClass('is-valid is-invalid');
    }
  });

  // Drag and drop for slides
  $('#uploadSection').on('dragover', function(e) {
    e.preventDefault();
    $(this).addClass('dragover');
  }).on('dragleave', function(e) {
    e.preventDefault();
    $(this).removeClass('dragover');
  }).on('drop', function(e) {
    e.preventDefault();
    $(this).removeClass('dragover');
    const files = e.originalEvent.dataTransfer.files;
    if (files.length) {
      slidesUpload[0].files = files;
      handleFileSelect(files);
    }
  });

  // Remove file function
  window.removeFile = function(index) {
    const dt = new DataTransfer();
    const files = slidesUpload[0].files;
    
    for (let i = 0; i < files.length; i++) {
      if (i !== index) {
        dt.items.add(files[i]);
      }
    }
    
    slidesUpload[0].files = dt.files;
    handleFileSelect(dt.files);
  };

  // Initialize
  updateStatus('Seamless recording system ready', 'info', SUCCESS_MESSAGE_TIMEOUT);
  updateSessionIndicator();

  // Keyboard shortcuts
  $(document).on('keydown', function(e) {
    if (e.code === 'Space' && !$(e.target).is('input, textarea')) {
      e.preventDefault();
      if (!isRecording) {
        micButton.click();
      } else {
        sendButton.click();
      }
    }
    
    if (e.code === 'Escape' && isRecording) {
      sendButton.click();
    }
  });

  // Prevent accidental page navigation during recording
  $(window).on('beforeunload', function(e) {
    if (isRecording) {
      const message = 'Recording in progress. Are you sure you want to leave?';
      e.returnValue = message;
      return message;
    }
  });

  // Enhanced modal handling
  $('#successModal').on('hidden.bs.modal', function() {
    updateStatus('Ready for new seamless recording', 'info', SUCCESS_MESSAGE_TIMEOUT);
  });

  // Debug logging for development
  if (window.location.hostname === 'localhost') {
    console.log('Seamless sermon recorder initialized');
    console.log('Session state:', {
      sessionActive,
      folderName,
      currentSermonTitle,
      currentSpeakerName,
      chunkDuration: CHUNK_DURATION_SECONDS + ' seconds'
    });
  }
});
</script>
</body>
</html>
