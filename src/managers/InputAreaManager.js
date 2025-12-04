/**
 * Manages the input box and the balls dropped into it.
 */
export class InputAreaManager {
    constructor (scene) {
        this.scene = scene;
        this.inputContainer = null;
        
        // Arrays to track state
        this.activeBalls = []; // Balls currently being typed (not yet confirmed)
        this.completedWords = []; // Array of Arrays (each inner array is a word)
        
        this.inputBg = null;
        this.clearBtn = null;
        this.playBtn = null;
        
        this.areaWidth = 600;
        this.areaHeight = 100;
        this.yPos = 0;
    }
    
    create () {
        const width = this.scene.scale.width;
        // Position near the top
        this.yPos = 100;
        
        this.inputContainer = this.scene.add.container(width / 2, this.yPos);
        this.drawLayout();
    }
    
    drawLayout () {
        const width = this.scene.scale.width;
        const isMobile = width < 700;
        
        this.areaWidth = isMobile ? width * 0.9 : width * 0.8;
        
        if (this.inputBg) this.inputBg.destroy();
        if (this.clearBtn) this.clearBtn.destroy();
        if (this.playBtn) this.playBtn.destroy();
        
        this.inputBg = this.scene.add.rectangle(0, 0, this.areaWidth, this.areaHeight, 0x222222);
        this.inputBg.setStrokeStyle(4, 0x00ffff);
        this.inputBg.setAlpha(0.8);
        
        this.inputContainer.add([this.inputBg]);
        this.inputContainer.sendToBack(this.inputBg);
        
        this.createClearButton(isMobile);
        
        if (this.scene.isSentenceMode) {
            this.createPlayButton(isMobile);
        }
        
        this.repositionBalls();
    }
    
    createClearButton (isMobile) {
        let btnX, btnY;
        
        if (isMobile) {
            btnX = -40;
            btnY = this.areaHeight / 2 + 30;
        } else {
            btnX = -40;
            btnY = this.areaHeight / 2 + 30;
        }
        
        this.clearBtn = this.scene.add.container(btnX, btnY);
        
        const bg = this.scene.add.rectangle(0, 0, 80, 40, 0xcc0000);
        bg.setStrokeStyle(2, 0xffffff);
        const text = this.scene.add.text(0, 0, "CLEAR", { fontSize: '16px', fontStyle: 'bold' }).setOrigin(0.5);
        
        this.clearBtn.add([bg, text]);
        this.clearBtn.setSize(80, 40);
        this.clearBtn.setInteractive({ useHandCursor: true });
        
        this.clearBtn.on('pointerdown', () => {
            this.scene.sound.play('click');
            this.clearInput();
        });
        
        this.inputContainer.add(this.clearBtn);
    }
    
    createPlayButton (isMobile) {
        let btnX, btnY;
        const spacing = 100; // Button height (40) + Gap (10)
        
        // Position relative to the Clear button
        if (isMobile) {
            btnX = -40 + spacing;
            btnY = this.areaHeight / 2 + 30;
        } else {
            btnX = -40 + spacing;
            btnY = this.areaHeight / 2 + 30;
        }

        this.playBtn = this.scene.add.container(btnX, btnY);
        
        // Match dimensions of Clear button (80x40), use Purple to distinguish
        const bg = this.scene.add.rectangle(0, 0, 80, 40, 0x9b59b6);
        bg.setStrokeStyle(2, 0xffffff);
        const text = this.scene.add.text(0, 0, "PLAY", { fontSize: '16px', fontStyle: 'bold' }).setOrigin(0.5);
        
        this.playBtn.add([bg, text]);
        this.playBtn.setSize(80, 40);
        this.playBtn.setInteractive({ useHandCursor: true });
        
        this.playBtn.on('pointerdown', () => {
            this.scene.sound.play('click');
            if (this.scene.playFullSentence) {
                this.scene.playFullSentence();
            }
        });
        
        this.inputContainer.add(this.playBtn);
    }
    
    addBall (ball) {
        if (ball.body) {
            ball.body.enable = false;
            ball.body.setVelocity(0, 0);
        }
        
        // Add to container logic
        const localX = ball.x - this.inputContainer.x;
        const localY = ball.y - this.inputContainer.y;
        
        this.inputContainer.add(ball);
        ball.setPosition(localX, localY);
        
        const ballImage = ball.list[0];
        ballImage.setTint(0x00cc44);
        
        ball.setInteractive({ useHandCursor: true });
        ball.off('pointerdown');
        
        // Interaction Logic:
        // If ball is in activeBalls, popping it acts as backspace/removal.
        // If ball is in completedWords, popping it removes the whole word.
        ball.on('pointerdown', () => {
            this.handleBallClick(ball);
        });
        
        this.activeBalls.push(ball);
        this.repositionBalls();
        
        // Notify scene with CURRENT word only
        this.scene.handleInputUpdate(this.getCurrentWord());
    }
    
    handleBallClick(ball) {
        // 1. Check if it's in active buffer
        if (this.activeBalls.includes(ball)) {
            this.popBall(ball);
            return;
        }
        
        // 2. Check if it's in a completed word (Sentence Mode)
        const wordIndex = this.completedWords.findIndex(wordArr => wordArr.includes(ball));
        if (wordIndex !== -1) {
            // Remove the whole word
            const wordBalls = this.completedWords[wordIndex];
            this.explodeBalls(wordBalls);
            
            // Remove from array
            this.completedWords.splice(wordIndex, 1);
            
            this.repositionBalls();
            this.scene.sound.play('drop_invalid', { volume: 0.5 });
        }
    }
    
    popBall (ball) {
        this.activeBalls = this.activeBalls.filter(b => b !== ball);
        
        const worldPos = this.inputContainer.localTransform.transformPoint(ball.x, ball.y);
        ball.setPosition(worldPos.x, worldPos.y);
        this.scene.add.existing(ball);
        
        this.explodeBalls([ball]);
        this.repositionBalls();
        this.scene.handleInputUpdate(this.getCurrentWord());
    }
    
    /**
     * Commits the current active balls as a completed word.
     * Used in Sentence Mode.
     */
    commitCurrentWord() {
        if (this.activeBalls.length === 0) return;
        
        // Change visual style to indicate "Locked"
        this.activeBalls.forEach(ball => {
            const ballImage = ball.list[0];
            if (ballImage) ballImage.setTint(0xffd700); // Gold tint for completed words
        });
        
        // Move to completed array
        this.completedWords.push([...this.activeBalls]);
        this.activeBalls = []; // Clear active buffer
        
        this.repositionBalls();
    }
    
    /**
     * Gets valid sentence for audio playback.
     */
    getAllCompletedWordsText() {
        return this.completedWords.map(wordArr => {
            return wordArr.map(b => b.char).join('');
        });
    }
    
    /**
     * Repositions balls to be Left Aligned.
     */
    repositionBalls () {
        const padding = 20;
        const gap = 55; // Space between letters
        const wordGap = 30; // Extra space between words
        
        // Start X position (Left aligned relative to container center)
        let currentX = -(this.areaWidth / 2) + 50;
        
        // 1. Position Completed Words
        this.completedWords.forEach(wordArr => {
            wordArr.forEach(ball => {
                this.scene.tweens.add({
                    targets: ball,
                    x: currentX,
                    y: 0,
                    duration: 200,
                    ease: 'Power2'
                });
                currentX += gap;
            });
            currentX += wordGap; // Add space after word
        });
        
        // 2. Position Active Balls
        this.activeBalls.forEach(ball => {
            this.scene.tweens.add({
                targets: ball,
                x: currentX,
                y: 0,
                duration: 200,
                ease: 'Power2'
            });
            currentX += gap;
        });
    }
    
    getCurrentWord () {
        return this.activeBalls.map(b => b.char).join('');
    }
    
    clearInput () {
        // Clear Active Balls
        if (this.activeBalls.length > 0) {
            const ballsToExplode = [...this.activeBalls];
            ballsToExplode.forEach(ball => this.moveBallToWorld(ball));
            this.explodeBalls(ballsToExplode);
            this.activeBalls = [];
        }
        
        // Clear Completed Words (Sentence Mode)
        if (this.completedWords.length > 0) {
            this.completedWords.forEach(wordArr => {
                wordArr.forEach(ball => this.moveBallToWorld(ball));
                this.explodeBalls(wordArr);
            });
            this.completedWords = [];
        }
        
        this.scene.handleInputUpdate("");
    }
    
    moveBallToWorld(ball) {
        const worldPos = this.inputContainer.localTransform.transformPoint(ball.x, ball.y);
        ball.setPosition(worldPos.x, worldPos.y);
        this.scene.add.existing(ball);
    }
    
    fillWord (fullWord) {
        // Only allow auto-fill if active buffer matches start
        const currentWord = this.getCurrentWord();
        if (!fullWord.startsWith(currentWord)) return;
        
        const remainingChars = fullWord.substring(currentWord.length).split('');
        
        remainingChars.forEach((char, index) => {
            this.scene.time.delayedCall(index * 100, () => {
                const spawnX = this.inputContainer.x;
                const spawnY = this.inputContainer.y - 100;
                
                const ball = this.scene.inputManager.spawnBall(spawnX, spawnY, char);
                this.addBall(ball);
                this.scene.sound.play('drop_valid', { volume: 0.5 });
            });
        });
    }
    
    explodeBalls (ballsToExplode) {
        if (!ballsToExplode || ballsToExplode.length === 0) return;
        
        const emitter = this.scene.add.particles(0, 0, 'particle', {
            speed: { min: 50, max: 200 },
            scale: { start: 1, end: 0 },
            alpha: { start: 1, end: 0 },
            lifespan: 800,
            gravityY: 200,
            blendMode: 'ADD',
            emitting: false
        });
        
        ballsToExplode.forEach(ball => {
            emitter.emitParticleAt(ball.x, ball.y, 20);
            ball.destroy();
        });
        
        this.scene.sound.play('bounce1', { volume: 0.5, rate: 1.5 });
        
        this.scene.time.delayedCall(1000, () => {
            emitter.destroy();
        });
    }
    
    getBounds () {
        const matrix = this.inputBg.getWorldTransformMatrix();
        return new Phaser.Geom.Rectangle(
          matrix.tx - this.areaWidth / 2,
          matrix.ty - this.areaHeight / 2,
          this.areaWidth,
          this.areaHeight
        );
    }
    
    resize (width, height) {
        this.yPos = Math.max(100, height * 0.15);
        this.inputContainer.setPosition(width / 2, this.yPos);
        this.drawLayout();
    }
}
