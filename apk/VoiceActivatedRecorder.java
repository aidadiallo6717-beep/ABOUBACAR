// VoiceActivatedRecorder.java
package com.ghost.core;

import android.media.AudioFormat;
import android.media.AudioRecord;
import android.media.MediaRecorder;
import android.os.Handler;
import android.os.HandlerThread;
import android.util.Log;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;

public class VoiceActivatedRecorder {
    
    private static final int SAMPLE_RATE = 16000;
    private static final int CHANNEL_CONFIG = AudioFormat.CHANNEL_IN_MONO;
    private static final int AUDIO_FORMAT = AudioFormat.ENCODING_PCM_16BIT;
    private static final int BUFFER_SIZE = AudioRecord.getMinBufferSize(
        SAMPLE_RATE, CHANNEL_CONFIG, AUDIO_FORMAT) * 2;
    
    private AudioRecord audioRecord;
    private boolean isRecording = false;
    private boolean isVoiceActive = false;
    private Handler backgroundHandler;
    private NetworkManager networkManager;
    
    private static final double VOICE_THRESHOLD = 0.01; // Seuil de détection vocale
    private static final int SILENCE_TIMEOUT = 3000; // 3 secondes de silence
    
    public VoiceActivatedRecorder(NetworkManager nm) {
        this.networkManager = nm;
        
        HandlerThread thread = new HandlerThread("VoiceRecorder");
        thread.start();
        backgroundHandler = new Handler(thread.getLooper());
        
        initAudioRecord();
    }
    
    private void initAudioRecord() {
        audioRecord = new AudioRecord(
            MediaRecorder.AudioSource.MIC,
            SAMPLE_RATE,
            CHANNEL_CONFIG,
            AUDIO_FORMAT,
            BUFFER_SIZE
        );
    }
    
    public void startListening() {
        if (audioRecord == null) {
            initAudioRecord();
        }
        
        isRecording = true;
        audioRecord.startRecording();
        
        backgroundHandler.post(new Runnable() {
            @Override
            public void run() {
                processAudio();
            }
        });
    }
    
    private void processAudio() {
        byte[] buffer = new byte[BUFFER_SIZE];
        File tempFile = null;
        FileOutputStream fos = null;
        long silenceStart = 0;
        
        while (isRecording) {
            int bytesRead = audioRecord.read(buffer, 0, buffer.length);
            
            // Calculer le niveau sonore
            double level = calculateRMSLevel(buffer, bytesRead);
            
            if (level > VOICE_THRESHOLD) {
                // Voix détectée
                if (!isVoiceActive) {
                    // Commencer un nouvel enregistrement
                    isVoiceActive = true;
                    try {
                        tempFile = File.createTempFile("voice_", ".pcm");
                        fos = new FileOutputStream(tempFile);
                    } catch (IOException e) {
                        e.printStackTrace();
                    }
                    silenceStart = 0;
                }
                
                // Écrire les données
                if (fos != null) {
                    try {
                        fos.write(buffer, 0, bytesRead);
                    } catch (IOException e) {
                        e.printStackTrace();
                    }
                }
                
            } else {
                // Silence
                if (isVoiceActive) {
                    if (silenceStart == 0) {
                        silenceStart = System.currentTimeMillis();
                    } else if (System.currentTimeMillis() - silenceStart > SILENCE_TIMEOUT) {
                        // Fin de la phrase
                        isVoiceActive = false;
                        silenceStart = 0;
                        
                        // Envoyer l'enregistrement
                        if (tempFile != null) {
                            sendRecording(tempFile);
                        }
                    }
                }
            }
        }
    }
    
    private double calculateRMSLevel(byte[] audioData, int bytesRead) {
        long sum = 0;
        for (int i = 0; i < bytesRead - 1; i += 2) {
            short sample = (short) ((audioData[i + 1] << 8) | (audioData[i] & 0xFF));
            sum += sample * sample;
        }
        return Math.sqrt(sum / (bytesRead / 2)) / 32768.0;
    }
    
    private void sendRecording(File file) {
        // Convertir PCM en MP3 et envoyer
        networkManager.sendFile(file, "audio");
    }
    
    public void stopListening() {
        isRecording = false;
        if (audioRecord != null) {
            audioRecord.stop();
            audioRecord.release();
            audioRecord = null;
        }
    }
}
