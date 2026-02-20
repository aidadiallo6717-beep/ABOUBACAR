// webrtc_stream.js - Intégration dans le panel
class WebRTCStream {
    constructor(deviceId) {
        this.deviceId = deviceId;
        this.peerConnection = null;
        this.dataChannel = null;
        this.streaming = false;
    }
    
    async startStream() {
        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };
        
        this.peerConnection = new RTCPeerConnection(configuration);
        
        // Canal de données pour les commandes
        this.dataChannel = this.peerConnection.createDataChannel('commands');
        this.dataChannel.onopen = () => {
            console.log('Data channel ouvert');
        };
        
        // Recevoir le flux vidéo
        this.peerConnection.ontrack = (event) => {
            const videoElement = document.getElementById('remoteVideo');
            videoElement.srcObject = event.streams[0];
        };
        
        // Créer une offre
        const offer = await this.peerConnection.createOffer();
        await this.peerConnection.setLocalDescription(offer);
        
        // Envoyer l'offre au payload via l'API
        const response = await fetch('/api/v1/webrtc.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': localStorage.getItem('apiKey')
            },
            body: JSON.stringify({
                action: 'offer',
                device_id: this.deviceId,
                sdp: this.peerConnection.localDescription
            })
        });
        
        const data = await response.json();
        
        // Appliquer la réponse
        await this.peerConnection.setRemoteDescription(data.answer);
        
        this.streaming = true;
    }
    
    sendCommand(command) {
        if (this.dataChannel && this.dataChannel.readyState === 'open') {
            this.dataChannel.send(JSON.stringify(command));
        }
    }
    
    stopStream() {
        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }
        this.streaming = false;
    }
}
