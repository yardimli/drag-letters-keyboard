import { KeyboardManager } from '../managers/KeyboardManager.js';
import { InputAreaManager } from '../managers/InputAreaManager.js';
import { InputManager } from '../managers/InputManager.js';

export class GameScene extends Phaser.Scene {
    constructor() {
        super({ key: 'GameScene' });
    }

    preload() {
        // Audio Assets
        this.load.audio('drop', 'assets/audio/DSGNBass_Smooth Sub Drop Bass Downer.wav');
        this.load.audio('bounce1', 'assets/audio/basketball_bounce_single_3.wav');
        this.load.audio('bounce2', 'assets/audio/basketball_bounce_single_5.wav');
        this.load.audio('bounce3', 'assets/audio/Vintage Bounce.wav');
        this.load.audio('click', 'assets/audio/basketball_bounce_single_5.wav');
        this.load.audio('click_drop', 'assets/audio/basketball_bounce_single_3.wav');
        this.load.audio('drop_valid', 'assets/audio/Drop Game Potion.wav');
        this.load.audio('drop_invalid', 'assets/audio/Hit Item Dropped 2.wav');
    }

    create() {
        // Fade in transition
        this.cameras.main.fadeIn(500, 0, 0, 0);

        this.createBackground();
        this.createBallTexture();
        this.createParticleTexture();

        // Data
        this.dictionary = this.registry.get('dictionary') || [];
        this.currentImage = null;

        // Managers
        this.keyboardManager = new KeyboardManager(this);
        this.inputAreaManager = new InputAreaManager(this);
        this.inputManager = new InputManager(this);

        // Initialize
        this.keyboardManager.create();
        this.inputAreaManager.create();
        this.inputManager.create();

        // Event Listeners
        this.scale.on('resize', this.resize, this);
    }

    createBackground() {
        const width = this.scale.width;
        const height = this.scale.height;
        this.bgGraphics = this.add.graphics();
        this.bgGraphics.fillGradientStyle(0x1a2a6c, 0xb21f1f, 0x000000, 0x000000, 1);
        this.bgGraphics.fillRect(0, 0, width, height);
        this.bgGraphics.setDepth(-100);
    }

    createBallTexture() {
        if (this.textures.exists('ball3d')) return;
        const size = 64;
        const texture = this.textures.createCanvas('ball3d', size, size);
        const context = texture.getContext();
        const centerX = size / 2;
        const centerY = size / 2;
        const radius = 25;

        const grd = context.createRadialGradient(centerX - 10, centerY - 10, 2, centerX, centerY, radius);
        grd.addColorStop(0, '#ffffff');
        grd.addColorStop(1, '#888888');

        context.fillStyle = grd;
        context.beginPath();
        context.arc(centerX, centerY, radius, 0, Math.PI * 2);
        context.fill();

        context.lineWidth = 3;
        context.strokeStyle = '#ffffff';
        context.stroke();

        texture.refresh();
    }

    createParticleTexture() {
        if (this.textures.exists('particle')) return;
        const size = 16;
        const texture = this.textures.createCanvas('particle', size, size);
        const context = texture.getContext();
        context.fillStyle = '#ffffff';
        context.beginPath();
        context.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
        context.fill();
        texture.refresh();
    }


    handleBallDrop(ball) {
        const bounds = this.inputAreaManager.getBounds();

        if (bounds.contains(ball.x, ball.y)) {
            this.sound.play('drop_valid');
            this.inputAreaManager.addBall(ball);
        } else {
            this.returnBallToKeyboard(ball);
        }
    }

    returnBallToKeyboard(ball) {
        this.sound.play('bounce1', { volume: 0.5 });

        if (ball.body) ball.body.enable = false;

        this.tweens.add({
            targets: ball,
            x: ball.dragStartX,
            y: ball.dragStartY,
            duration: 400,
            ease: 'Back.Out',
            onComplete: () => {
                ball.destroy();
            }
        });
    }

    checkWord(word) {
        // If the word changes or is cleared, remove the current image immediately
        if (this.currentImage) {
            this.currentImage.destroy();
            this.currentImage = null;
        }

        if (!word || word.length === 0) return;

        const match = this.dictionary.find(w => w.text === word);

        if (match) {
            this.showSuccess(match);
        }
    }

    showSuccess(wordObj) {
        this.sound.play('bounce3');

        if (wordObj.image) {
            if (this.textures.exists(wordObj.text)) {
                this.displayImage(wordObj.text);
            } else {
                this.load.image(wordObj.text, wordObj.image);
                this.load.once('complete', () => {
                    this.displayImage(wordObj.text);
                });
                this.load.start();
            }
        }
    }

    displayImage(key) {
        if (this.currentImage) this.currentImage.destroy();

        // --- Calculate Position: Right side of the input box ---
        // Input container is centered at (width/2, yPos)
        // Input width is 600. Right edge is x + 300.
        // We add some padding (e.g., 20px) + half the image width (approximate placeholder)

        const inputMgr = this.inputAreaManager;
        const inputRightEdge = inputMgr.inputContainer.x + (inputMgr.areaWidth / 2);
        const targetX = inputRightEdge + 10; // 10px padding from the box
        const targetY = inputMgr.inputContainer.y; // Aligned vertically with input box

        this.currentImage = this.add.image(targetX, targetY, key);
        this.currentImage.setOrigin(0, 0.5); // Origin left-center to grow outwards to the right

        // --- Resize to fit Input Area Height ---
        const targetHeight = inputMgr.areaHeight;
        // Scale based on height to match the input box
        const scale = targetHeight / this.currentImage.height;

        this.currentImage.setScale(0); // Start invisible for tween

        // Animate In
        this.tweens.add({
            targets: this.currentImage,
            scale: scale,
            duration: 500,
            ease: 'Back.Out'
        });

        // --- Auto-hide after 3 seconds ---
        this.time.delayedCall(3000, () => {
            if (this.currentImage && this.currentImage.active) {
                this.tweens.add({
                    targets: this.currentImage,
                    alpha: 0,
                    scale: 0,
                    duration: 300,
                    onComplete: () => {
                        if (this.currentImage) {
                            this.currentImage.destroy();
                            this.currentImage = null;
                        }
                    }
                });
            }
        });
    }

    update() {
        if (this.inputManager) {
            this.inputManager.update();
        }
    }

    resize(gameSize) {
        const width = gameSize.width;
        const height = gameSize.height;

        this.cameras.main.setViewport(0, 0, width, height);

        if (this.bgGraphics) {
            this.bgGraphics.clear();
            this.bgGraphics.fillGradientStyle(0x1a2a6c, 0xb21f1f, 0x000000, 0x000000, 1);
            this.bgGraphics.fillRect(0, 0, width, height);
        }

        this.keyboardManager.resize(width, height);
        this.inputAreaManager.resize(width, height);

        // If an image is currently displayed, update its position
        if (this.currentImage && this.currentImage.active) {
            const inputMgr = this.inputAreaManager;
            const inputRightEdge = inputMgr.inputContainer.x + (inputMgr.areaWidth / 2);
            this.currentImage.setPosition(inputRightEdge + 10, inputMgr.inputContainer.y);
        }
    }
}