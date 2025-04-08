// new ws connection
let ws = new WebSocket('/chat');
// Alternatively use absolute path, change DNS_NAME to your server's DNS name
// let ws = new WebSocket('wss://DNS_NAME/chat');

// dom refs
const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');
const startBtn = document.getElementById('startBtn');

const TRACK_WIDTH = 600;
const TRACK_LEFT = (canvas.width - TRACK_WIDTH) / 2;
const TRACK_TOP = 50;
const TRACK_HEIGHT = canvas.height - 100;

let playerColor = null;
let otherPlayer = null;
let myPlayer = { x: TRACK_LEFT + 50, y: 100 };


ws.addEventListener('open', () => {
    console.log('Connected to server');
});

ws.addEventListener('message', (event) => {
    const data = JSON.parse(event.data);

    if (data.type === 'assign-role') {
        playerColor = data.color;
        myPlayer.y = playerColor === 'red' ? 100 : 200;
    } else if (data.type === 'state-update') {
        otherPlayer = data.otherPlayer;
    }
});

function drawTrack() {
    ctx.fillStyle = '#777';
    ctx.fillRect(TRACK_LEFT, TRACK_TOP, TRACK_WIDTH, TRACK_HEIGHT);

    ctx.strokeStyle = 'white';
    ctx.lineWidth = 4;
    ctx.setLineDash([10, 10]);
    ctx.beginPath();
    ctx.moveTo(TRACK_LEFT + TRACK_WIDTH / 2, TRACK_TOP);
    ctx.lineTo(TRACK_LEFT + TRACK_WIDTH / 2, TRACK_TOP + TRACK_HEIGHT);
    ctx.stroke();
    ctx.setLineDash([]);
}

function drawPlayers() {
    if (playerColor) {
        ctx.fillStyle = playerColor;
        ctx.beginPath();
        ctx.arc(myPlayer.x, myPlayer.y, 20, 0, Math.PI * 2);
        ctx.fill();
    }

    if (otherPlayer) {
        ctx.fillStyle = otherPlayer.color || 'gray';
        ctx.beginPath();
        ctx.arc(otherPlayer.x, otherPlayer.y, 20, 0, Math.PI * 2);
        ctx.fill();
    }
}

function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    drawTrack();
    drawPlayers(); // отрисовать даже если ты ещё не получил цвет
}

function gameLoop() {
    if (gameRunning) {
        draw();
        requestAnimationFrame(gameLoop);
    }
}

let gameRunning = false;

document.addEventListener('keydown', (e) => {
    if (!gameRunning || !playerColor) return;

    const step = 5;
    if (e.key === 'w' || e.key === 'ArrowUp') myPlayer.y -= step;
    if (e.key === 's' || e.key === 'ArrowDown') myPlayer.y += step;
    if (e.key === 'a' || e.key === 'ArrowLeft') myPlayer.x -= step;
    if (e.key === 'd' || e.key === 'ArrowRight') myPlayer.x += step;

    ws.send(JSON.stringify({ type: 'move', player: myPlayer, color: playerColor }));
});

startBtn.addEventListener('click', () => {
    gameRunning = true;
    gameLoop();
});

draw();
