export class BallManager {
    constructor(scene) {
        this.scene = scene;
        this.balls = [];
        this.ballGroup = null; // Physics Group for collisions
    }

    createLetterBalls(word) {
        // Create a physics group to handle collisions easier
        this.ballGroup = this.scene.physics.add.group({
            bounceX: 0.8,
            bounceY: 0.8,
            collideWorldBounds: true
        });

        const totalLetters = word.length;

        for (let i = 0; i < totalLetters; i++) {
            const char = word[i];

            const rX = Phaser.Math.Between(50, this.scene.scale.width - 50);
            const minY = Math.max(this.scene.scale.height / 2, 250);
            const rY = Phaser.Math.Between(minY, this.scene.scale.height - 50);

            const ball = this.scene.add.container(rX, rY);
            ball.setSize(50, 50);

            // Use the generated 3D texture
            const ballImage = this.scene.add.image(0, 0, 'ball3d');
            ballImage.setTint(0x0077ff);

            const text = this.scene.add.text(0, 0, char, { fontSize: '32px', fontStyle: 'bold', color: '#ffffff' }).setOrigin(0.5);

            ball.add([ballImage, text]);

            this.ballGroup.add(ball);

            ball.body.setDrag(10);
            ball.body.setCircle(25);

            const vX = Phaser.Math.Between(-20, 20);
            const vY = Phaser.Math.Between(-30, -30);
            ball.body.setVelocity(vX, vY);

            ball.setInteractive({ draggable: true });
            ball.char = char;
            ball.isLocked = false;
            ball.isDragging = false;

            ball.body.onWorldBounds = true;

            this.balls.push(ball);
        }

        this.scene.physics.world.on('worldbounds', (body) => {
            if (this.balls.includes(body.gameObject) && body.speed > 10) {
                this.playBounceSound();
            }
        });
    }

    enableCollisions() {
        this.scene.physics.add.collider(this.ballGroup, this.ballGroup, (ball1, ball2) => {
            const relativeSpeed = ball1.body.speed + ball2.body.speed;
            if (relativeSpeed > 10) {
                this.playBounceSound();
            }
        });
    }

    playBounceSound() {
        const sounds = ['bounce1', 'bounce2', 'bounce3'];
        const key = Phaser.Utils.Array.GetRandom(sounds);
        this.scene.sound.play(key, { volume: 0.5 });
    }

    // NEW: Explodes specific balls (used for word completion)
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

    update() {
        if (this.balls) {
            this.balls.forEach(ball => {
                if (!ball.isLocked && !ball.isDragging && ball.body) {
                    if (ball.body.speed < 1) {
                        const vX = Phaser.Math.Between(-30, 30);
                        const vY = Phaser.Math.Between(-30, 30);
                        ball.body.setVelocity(vX, vY);
                    }
                }
            });
        }
    }

    clear() {
        if (this.balls) {
            this.balls.forEach(l => l.destroy());
        }
        this.balls = [];
        if (this.ballGroup) {
            this.ballGroup.clear(true, true);
        }
    }
}