import { KeyboardManager } from '../managers/KeyboardManager.js';
import { InputAreaManager } from '../managers/InputAreaManager.js';
import { InputManager } from '../managers/InputManager.js';

export class GameScene extends Phaser.Scene {
    constructor () {
        super({ key: 'GameScene' });
    }
    
    init (data) {
        // If a specific word object was passed from SelectionScene, use it to start immediately
        // (Logic for specific word start can be added here if needed, currently we just use the dictionary)
    }
    
    preload () {
        // Audio Assets (Effects)
        this.load.audio('drop', 'assets/audio/DSGNBass_Smooth Sub Drop Bass Downer.wav');
        this.load.audio('bounce1', 'assets/audio/basketball_bounce_single_3.wav');
        this.load.audio('bounce2', 'assets/audio/basketball_bounce_single_5.wav');
        this.load.audio('bounce3', 'assets/audio/Vintage Bounce.wav');
        this.load.audio('click', 'assets/audio/basketball_bounce_single_5.wav');
        this.load.audio('click_drop', 'assets/audio/basketball_bounce_single_3.wav');
        this.load.audio('drop_valid', 'assets/audio/Drop Game Potion.wav');
        this.load.audio('drop_invalid', 'assets/audio/Hit Item Dropped 2.wav');
    }
    
    create () {
        this.cameras.main.fadeIn(500, 0, 0, 0);
        
        this.createBackground();
        this.createBallTexture();
        this.createParticleTexture();
        
        const allWords = this.registry.get('dictionary') || [];
        const lang = this.registry.get('language') || 'en';
        const categories = this.registry.get('categories') || [];
        
        // Filter dictionary by Language AND Category
        this.dictionary = allWords.filter(w => {
            const wCat = w.category || 'Default';
            return w.lang === lang && (categories.length === 0 || categories.includes(wCat));
        });
        
        console.log(`Game Dictionary: ${this.dictionary.length} words for language '${lang}' and selected categories.`);
        
        this.currentImage = null;
        this.suggestionText = null;
        
        this.keyboardManager = new KeyboardManager(this);
        this.inputAreaManager = new InputAreaManager(this);
        this.inputManager = new InputManager(this);
        
        this.keyboardManager.create();
        this.inputAreaManager.create();
        this.inputManager.create();
        
        this.createSuggestionUI();
        this.handleInputUpdate("");
        
        this.scale.on('resize', this.resize, this);
    }
    
    createSuggestionUI () {
        const width = this.scale.width;
        const isMobile = width < 700;
        const offset = isMobile ? -130 : -100;
        
        this.suggestionText = this.add.text(width / 2, this.inputAreaManager.yPos - offset, "", {
            fontSize: '24px',
            fontStyle: 'italic',
            color: '#ffff00',
            backgroundColor: '#00000088',
            padding: { x: 10, y: 5 }
        }).setOrigin(0.5).setAlpha(0);
        
        this.suggestionText.setInteractive({ useHandCursor: true });
        
        this.suggestionText.on('pointerdown', () => {
            if (this.suggestionText.visible && this.suggestionText.targetWord) {
                this.sound.play('click');
                this.inputAreaManager.fillWord(this.suggestionText.targetWord);
            }
        });
    }
    
    createBackground () {
        const width = this.scale.width;
        const height = this.scale.height;
        this.bgGraphics = this.add.graphics();
        this.bgGraphics.fillGradientStyle(0x1a2a6c, 0xb21f1f, 0x000000, 0x000000, 1);
        this.bgGraphics.fillRect(0, 0, width, height);
        this.bgGraphics.setDepth(-100);
    }
    
    createBallTexture () {
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
    
    createParticleTexture () {
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
    
    handleBallDrop (ball) {
        const bounds = this.inputAreaManager.getBounds();
        
        if (bounds.contains(ball.x, ball.y)) {
            this.sound.play('drop_valid');
            this.inputAreaManager.addBall(ball);
        } else {
            this.returnBallToKeyboard(ball);
        }
    }
    
    returnBallToKeyboard (ball) {
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
    
    handleInputUpdate (currentWord) {
        this.checkWord(currentWord);
        
        const potentialMatches = this.dictionary.filter(w => w.text.startsWith(currentWord));
        const validNextChars = [];
        potentialMatches.forEach(w => {
            if (w.text.length > currentWord.length) {
                const nextChar = w.text[currentWord.length];
                if (!validNextChars.includes(nextChar)) {
                    validNextChars.push(nextChar);
                }
            }
        });
        
        this.keyboardManager.updateKeyAvailability(validNextChars);
        
        if (potentialMatches.length === 1 && potentialMatches[0].text.length > currentWord.length) {
            const match = potentialMatches[0];
            this.suggestionText.setText(`Suggestion: ${match.text}`);
            this.suggestionText.targetWord = match.text;
            this.suggestionText.setAlpha(1);
            
            this.tweens.add({
                targets: this.suggestionText,
                scale: { from: 1, to: 1.05 },
                yoyo: true,
                repeat: -1,
                duration: 800
            });
        } else {
            this.suggestionText.setAlpha(0);
            this.suggestionText.targetWord = null;
            this.tweens.killTweensOf(this.suggestionText);
            this.suggestionText.setScale(1);
        }
    }
    
    checkWord (word) {
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
    
    showSuccess (wordObj) {
        // 1. Play Success Sound Effect
        this.sound.play('bounce3');
        
        // 2. Load and Play Word Audio (TTS) if available
        if (wordObj.audio) {
            // Check if already loaded
            if (this.cache.audio.exists(wordObj.text + '_audio')) {
                this.sound.play(wordObj.text + '_audio');
            } else {
                // Dynamic load
                const loader = this.load.audio(wordObj.text + '_audio', wordObj.audio);
                loader.once('complete', () => {
                    this.sound.play(wordObj.text + '_audio');
                });
                loader.start();
            }
        }
        
        // 3. Display Image (Prefer Thumb)
        // Use thumb if available, otherwise fallback to full image
        const imageKey = wordObj.text;
        const imageSrc = wordObj.thumb || wordObj.image;
        
        if (imageSrc) {
            if (this.textures.exists(imageKey)) {
                this.displayImage(imageKey);
            } else {
                this.load.image(imageKey, imageSrc);
                this.load.once('complete', () => {
                    this.displayImage(imageKey);
                });
                this.load.start();
            }
        }
    }
    
    displayImage (key) {
        if (this.currentImage) this.currentImage.destroy();
        
        const width = this.scale.width;
        const isMobile = width < 700;
        const inputMgr = this.inputAreaManager;
        
        let targetX, targetY;
        let originX, originY;
        
        if (isMobile) {
            targetX = width / 2;
            targetY = inputMgr.inputContainer.y + (inputMgr.areaHeight / 2) + 70;
            originX = 0.5;
            originY = 0;
        } else {
            const inputRightEdge = inputMgr.inputContainer.x + (inputMgr.areaWidth / 2);
            targetX = inputRightEdge + 10;
            targetY = inputMgr.inputContainer.y;
            originX = 0;
            originY = 0.5;
        }
        
        this.currentImage = this.add.image(targetX, targetY, key);
        this.currentImage.setOrigin(originX, originY);
        
        const targetHeight = inputMgr.areaHeight;
        const scale = targetHeight / this.currentImage.height;
        
        this.currentImage.setScale(0);
        
        this.tweens.add({
            targets: this.currentImage,
            scale: scale,
            duration: 500,
            ease: 'Back.Out'
        });
        
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
    
    update () {
        if (this.inputManager) {
            this.inputManager.update();
        }
    }
    
    resize (gameSize) {
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
        
        if (this.currentImage && this.currentImage.active) {
            const isMobile = width < 700;
            const inputMgr = this.inputAreaManager;
            
            if (isMobile) {
                this.currentImage.setOrigin(0.5, 0);
                this.currentImage.setPosition(width / 2, inputMgr.inputContainer.y + (inputMgr.areaHeight / 2) + 70);
            } else {
                this.currentImage.setOrigin(0, 0.5);
                const inputRightEdge = inputMgr.inputContainer.x + (inputMgr.areaWidth / 2);
                this.currentImage.setPosition(inputRightEdge + 10, inputMgr.inputContainer.y);
            }
        }
        
        if (this.suggestionText) {
            const isMobile = width < 700;
            const offset = isMobile ? -100 : 80;
            this.suggestionText.setPosition(width / 2, this.inputAreaManager.yPos - offset);
        }
        
        const currentWord = this.inputAreaManager.getCurrentWord();
        this.handleInputUpdate(currentWord);
    }
}
