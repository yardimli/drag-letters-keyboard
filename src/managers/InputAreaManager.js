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
        const height = this.scene.scale.height;
        this.yPos =  100;

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
        ball.on('pointerdown', () => {
            this.popBall(ball);
        });

        this.activeBalls.push(ball);
        this.repositionBalls();

        this.scene.checkWord(this.getCurrentWord());
    }

    popBall(ball) {
        this.scene.sound.play('bounce1', { rate: 1.5, volume: 0.5 });

        this.activeBalls = this.activeBalls.filter(b => b !== ball);

        const worldStart = this.inputContainer.localTransform.transformPoint(ball.x, ball.y);

        this.scene.add.existing(ball);
        ball.setPosition(worldStart.x, worldStart.y);

        this.scene.tweens.add({
            targets: ball,
            // MODIFIED: Use dragStartX/Y
            x: ball.dragStartX,
            y: ball.dragStartY,
            scale: 0.5,
            alpha: 0,
            duration: 300,
            onComplete: () => {
                ball.destroy();
                this.repositionBalls();
                this.scene.checkWord(this.getCurrentWord());
            }
        });
    }

    repositionBalls() {
        const count = this.activeBalls.length;
        if (count === 0) return;

        const gap = 70;
        const totalW = (count - 1) * gap;
        const startX = -totalW / 2;

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
        [...this.activeBalls].forEach(ball => this.popBall(ball));
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