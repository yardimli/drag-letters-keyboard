/**
 * Manages the input box and the balls dropped into it.
 */
export class InputAreaManager {
    constructor (scene) {
        this.scene = scene;
        this.inputContainer = null;
        this.activeBalls = [];
        this.inputBg = null;
        this.clearBtn = null;

        this.areaWidth = 600;
        this.areaHeight = 100;
        this.yPos = 0;
    }

    create () {
        const width = this.scene.scale.width;
        // Position near the top to allow space for image and keyboard below
        this.yPos = 100;

        // Container holds background, button, and balls
        this.inputContainer = this.scene.add.container(width / 2, this.yPos);

        this.drawLayout();
    }

    /**
     * Draws or redraws the static UI elements (Background, Button)
     * based on the current screen size.
     */
    drawLayout () {
        const width = this.scene.scale.width;
        const isMobile = width < 700;

        // 1. Determine Dimensions
        // On mobile, use 90% of screen width. On desktop, cap at 600.
        this.areaWidth = isMobile ? width * 0.9 : width * 0.8;

        // 2. Cleanup existing UI elements (but keep balls)
        if (this.inputBg) this.inputBg.destroy();
        if (this.clearBtn) this.clearBtn.destroy();

        // 3. Create Background
        this.inputBg = this.scene.add.rectangle(0, 0, this.areaWidth, this.areaHeight, 0x222222);
        this.inputBg.setStrokeStyle(4, 0x00ffff);
        this.inputBg.setAlpha(0.8);
        
        // Add to container (send to back so balls appear on top)
        this.inputContainer.add([this.inputBg]);
        this.inputContainer.sendToBack(this.inputBg);

        // 5. Create Clear Button
        this.createClearButton(isMobile);

        // 6. Re-align any existing balls
        this.repositionBalls();
    }

    createClearButton (isMobile) {
        let btnX, btnY;

        if (isMobile) {
            // Under the input box
            btnX = 0;
            btnY = this.areaHeight / 2 + 30;
        } else {
            // To the right of the input box
            btnX = this.areaWidth / 2 + 60;
            btnY = 0;
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

    addBall (ball) {
        if (ball.body) {
            ball.body.enable = false;
            ball.body.setVelocity(0, 0);
        }

        const localX = ball.x - this.inputContainer.x;
        const localY = ball.y - this.inputContainer.y;

        this.inputContainer.add(ball);
        ball.setPosition(localX, localY);

        const ballImage = ball.list[0];
        ballImage.setTint(0x00cc44);

        ball.setInteractive({ useHandCursor: true });

        // Remove previous listeners to avoid duplicates
        ball.off('pointerdown');
        ball.on('pointerdown', () => {
            this.popBall(ball);
        });

        this.activeBalls.push(ball);
        this.repositionBalls();

        // Notify scene to update logic (check word, update keyboard)
        this.scene.handleInputUpdate(this.getCurrentWord());
    }

    /**
     * Removes a specific ball with an explosion effect.
     * @param {Phaser.GameObjects.Container} ball
     */
    popBall (ball) {
        // Remove from active array
        this.activeBalls = this.activeBalls.filter(b => b !== ball);

        // Convert local position to world position for the explosion
        const worldPos = this.inputContainer.localTransform.transformPoint(ball.x, ball.y);
        ball.setPosition(worldPos.x, worldPos.y);
        this.scene.add.existing(ball); // Move out of container to world

        // Trigger explosion effect
        this.explodeBalls([ball]);

        this.repositionBalls();

        // Notify scene to update logic
        this.scene.handleInputUpdate(this.getCurrentWord());
    }

    /**
     * Repositions balls to be Left Aligned.
     * Dynamically adjusts gap if balls exceed width.
     */
    repositionBalls () {
        const count = this.activeBalls.length;
        if (count === 0) return;

        // Default gap
        let gap = 70;
        const padding = 50;
        const availableWidth = this.areaWidth - (padding * 2);

        // If balls take up too much space, shrink the gap
        if ((count * gap) > availableWidth) {
            gap = availableWidth / count;
        }

        // Center the group of balls within the area
        const totalGroupWidth = (count - 1) * gap;
        const startX = -totalGroupWidth / 2;

        this.activeBalls.forEach((ball, index) => {
            const targetX = startX + (index * gap);

            this.scene.tweens.add({
                targets: ball,
                x: targetX,
                y: 0,
                duration: 200,
                ease: 'Power2'
            });
        });
    }

    getCurrentWord () {
        return this.activeBalls.map(b => b.char).join('');
    }

    clearInput () {
        if (this.activeBalls.length > 0) {
            const ballsToExplode = [...this.activeBalls];

            ballsToExplode.forEach(ball => {
                const worldPos = this.inputContainer.localTransform.transformPoint(ball.x, ball.y);
                ball.setPosition(worldPos.x, worldPos.y);
                this.scene.add.existing(ball);
            });

            this.explodeBalls(ballsToExplode);
            this.activeBalls = [];

            // Notify scene
            this.scene.handleInputUpdate("");
        }
    }

    /**
     * Auto-completes the word by spawning balls for the remaining characters.
     * @param {string} fullWord - The target word to complete.
     */
    fillWord (fullWord) {
        const currentWord = this.getCurrentWord();

        // Safety check: ensure fullWord starts with currentWord
        if (!fullWord.startsWith(currentWord)) return;

        const remainingChars = fullWord.substring(currentWord.length).split('');

        remainingChars.forEach((char, index) => {
            // Delay slightly for visual effect
            this.scene.time.delayedCall(index * 100, () => {
                const spawnX = this.inputContainer.x;
                const spawnY = this.inputContainer.y - 100; // Start slightly above

                const ball = this.scene.inputManager.spawnBall(spawnX, spawnY, char);

                // Add it to the input area immediately
                this.addBall(ball);

                // Play sound
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
            this.scene.sound.play('bounce1', { volume: 0.5, rate: 1.5 });
            ball.destroy();
        });

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
        // Keep the input box near the top (100px or 15% of height) to allow space below
        this.yPos = Math.max(100, height * 0.15);
        this.inputContainer.setPosition(width / 2, this.yPos);

        // Redraw layout to adjust width and button position
        this.drawLayout();
    }
}
