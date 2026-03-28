class PlugsSSE {
    constructor(host = 'http://localhost:8080', topics = []) {
        this.host = host;
        this.topics = topics;
        this.listeners = {};
        this.source = null;
        if (this.topics.length > 0) this.connect();
    }
    connect() {
        if (this.source) this.source.close();
        const url = `${this.host}/api/stream?topics=${this.topics.join(',')}`;
        this.source = new EventSource(url);
        this.source.onerror = () => {
            this.source.close();
            setTimeout(() => this.connect(), 3000);
        };
        for (const [topic, callbacks] of Object.entries(this.listeners)) {
            this.bindEvent(topic, callbacks);
        }
    }
    bindEvent(topic, callbacks) {
        callbacks.forEach(cb => {
            this.source.addEventListener(topic, (e) => {
                if (e.data === ':') return;
                try { cb(JSON.parse(e.data), e.lastEventId); } catch (err) {}
            });
        });
    }
    listen(topic, callback) {
        if (!this.topics.includes(topic)) {
            this.topics.push(topic);
            this.connect(); 
        }
        if (!this.listeners[topic]) this.listeners[topic] = [];
        this.listeners[topic].push(callback);
        if (this.source && this.source.readyState !== EventSource.CLOSED) {
            this.bindEvent(topic, [callback]);
        }
    }
}
if (!window.PlugsSSE) {
    window.PlugsSSE = PlugsSSE;
}

if (!window.plugsSSE) {
    // Default to origin to support the /api/stream proxy by default
    window.plugsSSE = new PlugsSSE(window.location.origin);
}
