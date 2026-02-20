// anti_debug.c - Code natif pour anti-débogage
#include <jni.h>
#include <android/log.h>
#include <sys/ptrace.h>
#include <unistd.h>
#include <sys/syscall.h>

#define LOG_TAG "NativeLib"
#define LOGD(...) __android_log_print(ANDROID_LOG_DEBUG, LOG_TAG, __VA_ARGS__)

JNIEXPORT jboolean JNICALL
Java_com_ghost_core_utils_NativeLib_checkDebug(JNIEnv *env, jobject thiz) {
    // Vérifier ptrace
    if (ptrace(PTRACE_TRACEME, 0, 1, 0) < 0) {
        return JNI_TRUE; // Déjà tracé
    }
    
    // Vérifier TracerPid
    char path[256];
    char line[256];
    FILE *fp;
    
    sprintf(path, "/proc/%d/status", getpid());
    fp = fopen(path, "r");
    
    if (fp) {
        while (fgets(line, sizeof(line), fp)) {
            if (strncmp(line, "TracerPid:", 10) == 0) {
                int pid = atoi(line + 10);
                fclose(fp);
                return pid != 0;
            }
        }
        fclose(fp);
    }
    
    return JNI_FALSE;
}

JNIEXPORT jboolean JNICALL
Java_com_ghost_core_utils_NativeLib_isEmulator(JNIEnv *env, jobject thiz) {
    // Vérifier les propriétés de l'émulateur
    char prop[256];
    
    FILE *fp = fopen("/proc/cpuinfo", "r");
    if (fp) {
        while (fgets(prop, sizeof(prop), fp)) {
            if (strstr(prop, "goldfish") || strstr(prop, "ranchu")) {
                fclose(fp);
                return JNI_TRUE;
            }
        }
        fclose(fp);
    }
    
    fp = fopen("/system/build.prop", "r");
    if (fp) {
        while (fgets(prop, sizeof(prop), fp)) {
            if (strstr(prop, "ro.kernel.qemu") || 
                strstr(prop, "ro.secure=0") ||
                strstr(prop, "ro.debuggable=1")) {
                fclose(fp);
                return JNI_TRUE;
            }
        }
        fclose(fp);
    }
    
    return JNI_FALSE;
}

JNIEXPORT jstring JNICALL
Java_com_ghost_core_utils_NativeLib_decryptString(JNIEnv *env, jobject thiz, jbyteArray data) {
    // Déchiffrement simple XOR
    jsize len = (*env)->GetArrayLength(env, data);
    jbyte *bytes = (*env)->GetByteArrayElements(env, data, NULL);
    
    char key[] = "GHOST_SECRET_KEY_2026";
    int key_len = strlen(key);
    
    for (int i = 0; i < len; i++) {
        bytes[i] ^= key[i % key_len];
    }
    
    jstring result = (*env)->NewStringUTF(env, (char*)bytes);
    (*env)->ReleaseByteArrayElements(env, data, bytes, JNI_ABORT);
    
    return result;
}
