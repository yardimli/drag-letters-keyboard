import { KeyboardManager } from '../managers/KeyboardManager.js';
import { InputAreaManager } from '../managers/InputAreaManager.js';
import { InputManager } from '../managers/InputManager.js';

export class GameScene extends Phaser.Scene {
    constructor () {
        super({ key: 'GameScene' });
    }
    
    init () {
        // Read sentence mode setting
        this.isSentenceMode = this.registry.get('sentenceMode') || false;
    }
    
    preload () {
        // Audio Assets
        this.load.audio('drop', 'assets/audio/DSGNBass_Smooth Sub Drop Bass Downer.wav');
        this.load.audio('bounce1', 'assets/audio/basketball_bounce_single_3.wav');
        this.load.audio('bounce3', 'assets/audio/Vintage Bounce.wav');
        this.load.audio('click', 'assets/audio/basketball_bounce_single_5.wav');
        this.load.audio('drop_valid', 'assets/audio/Drop Game Potion.wav');
        this.load.audio('drop_invalid', 'assets/audio/Hit Item Dropped 2.wav');
    }
    
    create () {
        this.cameras.main.fadeIn(500, 0, 0, 0);
        
        this.createBackground();
        this.createBallTexture();
        this.createParticleTexture();
        this.createPlaceholderTexture();
        
        const allWords = this.registry.get('dictionary') || [];
        const lang = this.registry.get('language') || 'en';
        const categories = this.registry.get('categories') || [];
        
        this.dictionary = allWords.filter(w => {
            const wCat = w.category || 'Default';
            return w.lang === lang && (categories.length === 0 || categories.includes(wCat));
        });
        
        this.currentImage = null;
        this.suggestionText = null;
        this.playSentenceBtn = null;
        
        this.keyboardManager = new KeyboardManager(this);
        this.inputAreaManager = new InputAreaManager(this);
        this.inputManager = new InputManager(this);
        
        this.keyboardManager.create();
        this.inputAreaManager.create();
        this.inputManager.create();
        
        this.createSuggestionUI();
        if (this.isSentenceMode) {
            this.createSentenceUI();
        }
        
        // Initialize keyboard state
        this.handleInputUpdate("");
        
        this.scale.on('resize', this.resize, this);
    }
    
    createSentenceUI () {
        const width = this.scale.width;
        // Button to play full sentence
        this.playSentenceBtn = this.add.container(width - 100, 50);
        
        const bg = this.add.circle(0, 0, 30, 0x9b59b6);
        bg.setStrokeStyle(2, 0xffffff);
        const icon = this.add.text(0, 0, "▶", { fontSize: '24px', color: '#fff' }).setOrigin(0.5);
        
        this.playSentenceBtn.add([bg, icon]);
        this.playSentenceBtn.setSize(60, 60);
        this.playSentenceBtn.setInteractive({ useHandCursor: true });
        
        this.playSentenceBtn.on('pointerdown', () => {
            this.playFullSentence();
        });
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
    
    createPlaceholderTexture() {
        if (this.textures.exists('placeholder_img')) return;
        const w = 256;
        const h = 256;
        const texture = this.textures.createCanvas('placeholder_img', w, h);
        const ctx = texture.getContext();
        
        // Draw standard grey background
        ctx.fillStyle = "#444";
        ctx.fillRect(0, 0, w, h);
        
        // Draw '?'
        ctx.fillStyle = "#666";
        ctx.font = "100px Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("?", w/2, h/2);
        
        ctx.strokeStyle = "#888";
        ctx.lineWidth = 10;
        ctx.strokeRect(5, 5, w-10, h-10);
        
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
            onComplete: () => ball.destroy()
        });
    }
    
    handleInputUpdate (currentWord) {
        // 1. Check if current input matches a word exactly
        // Returns TRUE if the word was consumed and the input reset (Sentence Mode)
        const wasConsumed = this.checkWord(currentWord);
        
        // FIX: If the word was consumed, the active buffer is now empty.
        // The recursive call inside showSuccess() has already reset the keyboard for the empty state.
        // We must return here to prevent this execution (which holds the old 'currentWord')
        // from re-evaluating and disabling the keyboard.
        if (wasConsumed) return;
        
        // 2. Filter suggestion dictionary based on current input
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
        
        // 3. Update Suggestion Text
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
        if (!word || word.length === 0) return false;
        
        const match = this.dictionary.find(w => w.text === word);
        
        if (match) {
            return this.showSuccess(match);
        }
        return false;
    }
    
    /**
     * Handles success logic.
     * @returns {boolean} True if the input buffer was consumed/reset (Sentence Mode), False otherwise.
     */
    showSuccess (wordObj) {
        // Visual/Audio Feedback
        this.sound.play('bounce3');
        this.playWordAudio(wordObj);
        this.showImage(wordObj);
        
        if (this.isSentenceMode) {
            // Commit logic for sentence mode
            this.inputAreaManager.commitCurrentWord();
            
            // Reset input prediction state immediately for the next word
            // This enables all starting letters again
            this.handleInputUpdate("");
            
            return true; // Signal that reset occurred
        } else {
            // Single Word Mode: Clear after 3 seconds
            if (this.clearTimer) this.time.removeEvent(this.clearTimer);
            
            this.clearTimer = this.time.delayedCall(3000, () => {
                this.inputAreaManager.clearInput();
                // Also hide image
                if (this.currentImage) {
                    this.tweens.add({
                        targets: this.currentImage,
                        alpha: 0,
                        duration: 300,
                        onComplete: () => {
                            if (this.currentImage) this.currentImage.destroy();
                            this.currentImage = null;
                        }
                    });
                }
            });
            
            return false; // Not consumed immediately
        }
    }
    
    playWordAudio(wordObj, callback) {
        if (!wordObj || !wordObj.audio) {
            if (callback) callback();
            return;
        }
        
        const key = wordObj.text + '_audio';
        
        const play = () => {
            this.sound.play(key);
            if (callback) {
                // Get duration to trigger callback
                const instance = this.sound.get(key);
                if (instance) {
                    const duration = instance.duration || 1.5;
                    this.time.delayedCall(duration * 1000, callback);
                } else {
                    this.time.delayedCall(1500, callback);
                }
            }
        };
        
        if (this.cache.audio.exists(key)) {
            play();
        } else {
            const loader = this.load.audio(key, wordObj.audio);
            loader.once('complete', play);
            loader.start();
        }
    }
    
    playFullSentence() {
        const wordsText = this.inputAreaManager.getAllCompletedWordsText();
        if (wordsText.length === 0) return;
        
        let index = 0;
        const playNext = () => {
            if (index >= wordsText.length) return;
            const text = wordsText[index];
            const wordObj = this.dictionary.find(w => w.text === text);
            index++;
            
            if (wordObj) {
                this.playWordAudio(wordObj, playNext);
            } else {
                playNext();
            }
        };
        
        playNext();
    }
    
    showImage (wordObj) {
        if (this.currentImage) this.currentImage.destroy();
        
        const width = this.scale.width;
        const isMobile = width < 700;
        const inputMgr = this.inputAreaManager;
        
        let targetX, targetY;
        let originX, originY;
        
        // Position image relative to input box
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
        
        // Determine Texture Key (Use placeholder if no image)
        let imageKey = 'placeholder_img';
        const imageSrc = wordObj.thumb || wordObj.image;
        
        if (imageSrc) {
            imageKey = wordObj.text;
            if (!this.textures.exists(imageKey)) {
                this.load.image(imageKey, imageSrc);
                this.load.once('complete', () => {
                    this.displayImageEntity(imageKey, targetX, targetY, originX, originY, inputMgr.areaHeight);
                });
                this.load.start();
                return;
            }
        }
        
        this.displayImageEntity(imageKey, targetX, targetY, originX, originY, inputMgr.areaHeight);
    }
    
    displayImageEntity(key, x, y, ox, oy, targetHeight) {
        if (this.currentImage) this.currentImage.destroy();
        
        this.currentImage = this.add.image(x, y, key);
        this.currentImage.setOrigin(ox, oy);
        
        // Scale to fit input height
        const scale = targetHeight / this.currentImage.height;
        this.currentImage.setScale(0);
        
        this.tweens.add({
            targets: this.currentImage,
            scale: scale,
            duration: 500,
            ease: 'Back.Out'
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
        
        if (this.playSentenceBtn) {
            this.playSentenceBtn.setPosition(width - 100, 50);
        }
    }
}
