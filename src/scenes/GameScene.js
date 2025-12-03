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

        // Data
        this.dictionary = this.registry.get('dictionary') || [];
        this.currentImage = null;
        this.currentWordTextObj = null;

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

        this.createWordDisplay();
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

    createWordDisplay() {
        this.currentWordTextObj = this.add.text(this.scale.width / 2, 100, "", {
            fontSize: '48px',
            fontStyle: 'bold',
            color: '#00ff00',
            stroke: '#000000',
            strokeThickness: 4
        }).setOrigin(0.5);
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
            // MODIFIED: Use dragStartX/Y
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
        if (this.currentImage) {
            this.currentImage.destroy();
            this.currentImage = null;
        }

        this.currentWordTextObj.setText("");

        if (!word || word.length === 0) return;

        const match = this.dictionary.find(w => w.text === word);

        if (match) {
            this.showSuccess(match);
        }
    }

    showSuccess(wordObj) {
        this.sound.play('bounce3');

        this.currentWordTextObj.setText(wordObj.text);

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

        const cx = this.scale.width / 2;
        const cy = this.scale.height / 2 - 100;

        this.currentImage = this.add.image(cx, cy, key);
        this.currentImage.setOrigin(0.5);

        const maxSize = 300;
        if (this.currentImage.width > maxSize || this.currentImage.height > maxSize) {
            const scale = maxSize / Math.max(this.currentImage.width, this.currentImage.height);
            this.currentImage.setScale(scale);
        }

        this.currentImage.setScale(0);
        this.tweens.add({
            targets: this.currentImage,
            scale: { from: 0, to: this.currentImage.scale },
            duration: 500,
            ease: 'Back.Out'
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

        if (this.currentWordTextObj) {
            this.currentWordTextObj.setPosition(width / 2, 100);
        }

        if (this.currentImage) {
            this.currentImage.setPosition(width / 2, height / 2 - 100);
        }
    }
}