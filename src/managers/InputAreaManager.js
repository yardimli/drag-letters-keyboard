/**
 * Manages the input box and the balls dropped into it.
 */
export class InputAreaManager {
    constructor(scene) {
        this.scene = scene;
        this.inputContainer = null;
        this.activeBalls = [];
        this.inputBg = null;

        this.areaWidth = 600;
        this.areaHeight = 100;
        this.yPos = 0;
    }

    create() {
        const width = this.scene.scale.width;
        // const height = this.scene.scale.height; // Unused
        this.yPos = 100;

        this.inputContainer = this.scene.add.container(width / 2, this.yPos);

        this.inputBg = this.scene.add.rectangle(0, 0, this.areaWidth, this.areaHeight, 0x222222);
        this.inputBg.setStrokeStyle(4, 0x00ffff);
        this.inputBg.setAlpha(0.8);

        const label = this.scene.add.text(-this.areaWidth / 2, -this.areaHeight / 2 - 30, "DRAG LETTERS HERE", {
            fontSize: '16px',
            color: '#00ffff'
        });

        this.inputContainer.add([this.inputBg, label]);

        this.createClearButton();
    }

    createClearButton() {
        // Position button slightly to the right of the input box
        const btnX = this.areaWidth / 2 + 60;
        const btn = this.scene.add.container(btnX, 0);

        const bg = this.scene.add.rectangle(0, 0, 80, 40, 0xcc0000);
        bg.setStrokeStyle(2, 0xffffff);
        const text = this.scene.add.text(0, 0, "CLEAR", { fontSize: '16px', fontStyle: 'bold' }).setOrigin(0.5);

        btn.add([bg, text]);
        btn.setSize(80, 40);
        btn.setInteractive({ useHandCursor: true });

        btn.on('pointerdown', () => {
            this.scene.sound.play('click');
            this.clearInput();
        });

        this.inputContainer.add(btn);
    }

    addBall(ball) {
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

        this.scene.checkWord(this.getCurrentWord());
    }

    /**
     * Removes a specific ball with an explosion effect.
     * @param {Phaser.GameObjects.Container} ball
     */
    popBall(ball) {
        // Remove from active array
        this.activeBalls = this.activeBalls.filter(b => b !== ball);

        // Convert local position to world position for the explosion
        const worldPos = this.inputContainer.localTransform.transformPoint(ball.x, ball.y);
        ball.setPosition(worldPos.x, worldPos.y);
        this.scene.add.existing(ball); // Move out of container to world

        // Trigger explosion effect
        this.explodeBalls([ball]);

        this.repositionBalls();
        this.scene.checkWord(this.getCurrentWord());
    }

    /**
     * Repositions balls to be Left Aligned.
     */
    repositionBalls() {
        const count = this.activeBalls.length;
        if (count === 0) return;

        const gap = 70;
        // Start from the left edge of the input area plus some padding
        const startX = (-this.areaWidth / 2) + 50;

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

    getCurrentWord() {
        return this.activeBalls.map(b => b.char).join('');
    }

    clearInput() {
        // Explode all active balls
        if (this.activeBalls.length > 0) {
            // Move balls to world space first for correct particle positioning
            const ballsToExplode = [...this.activeBalls];

            ballsToExplode.forEach(ball => {
                const worldPos = this.inputContainer.localTransform.transformPoint(ball.x, ball.y);
                ball.setPosition(worldPos.x, worldPos.y);
                this.scene.add.existing(ball);
            });

            this.explodeBalls(ballsToExplode);
            this.activeBalls = [];
            this.scene.checkWord("");
        }
    }

    /**
     * Explodes specific balls.
     * @param {Array} ballsToExplode
     */
    explodeBalls(ballsToExplode) {
        if (!ballsToExplode || ballsToExplode.length === 0) return;

        // Create a particle emitter manager
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
            // Emit particles at ball position
            emitter.emitParticleAt(ball.x, ball.y, 20); // 20 particles per ball

            // Play a sound (pitch shifted up for effect)
            this.scene.sound.play('bounce1', { volume: 0.5, rate: 1.5 });

            // Destroy the ball
            ball.destroy();
        });

        // Clean up the emitter after particles are gone
        this.scene.time.delayedCall(1000, () => {
            emitter.destroy();
        });
    }

    getBounds() {
        const matrix = this.inputBg.getWorldTransformMatrix();
        return new Phaser.Geom.Rectangle(
            matrix.tx - this.areaWidth / 2,
            matrix.ty - this.areaHeight / 2,
            this.areaWidth,
            this.areaHeight
        );
    }

    resize(width, height) {
        this.yPos = height / 2 + 50;
        this.inputContainer.setPosition(width / 2, this.yPos);
    }
}