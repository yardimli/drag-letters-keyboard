/**
 * Manages the visual keyboard at the bottom using 3D balls.
 */
export class KeyboardManager {
    constructor(scene) {
        this.scene = scene;
        this.keys = [];
        this.keyGroup = this.scene.add.container();

        // Configuration
        this.letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split('');
        this.keySize = 60; // Size of the ball
        this.padding = 10;
    }

    create() {
        this.drawKeyboard();
    }

    drawKeyboard() {
        this.keyGroup.removeAll(true);
        this.keys = [];

        const screenWidth = this.scene.scale.width;
        const screenHeight = this.scene.scale.height;

        // Layout settings
        const maxCols = 10;
        const totalWidth = (maxCols * this.keySize) + ((maxCols - 1) * this.padding);
        const startX = (screenWidth - totalWidth) / 2 + (this.keySize / 2);
        const startY = screenHeight - 250;

        this.letters.forEach((char, index) => {
            const col = index % maxCols;
            const row = Math.floor(index / maxCols);

            const x = startX + (col * (this.keySize + this.padding));
            const y = startY + (row * (this.keySize + this.padding));

            const keyBall = this.createKeyBall(x, y, char);
            this.keyGroup.add(keyBall);
            this.keys.push(keyBall);
        });
    }

    createKeyBall(x, y, char) {
        const container = this.scene.add.container(x, y);
        container.setSize(this.keySize, this.keySize);

        // 1. The Ball Image (Visual only)
        const ballImage = this.scene.add.image(0, 0, 'ball3d');
        ballImage.setTint(0x0077ff); // Default blue tint
        ballImage.setDisplaySize(this.keySize, this.keySize);

        // 2. The Text
        const text = this.scene.add.text(0, 0, char, {
            fontSize: '32px',
            fontStyle: 'bold',
            color: '#ffffff'
        }).setOrigin(0.5);

        container.add([ballImage, text]);

        // Interaction
        // MODIFIED: Added draggable: true so drag events fire
        container.setInteractive({ draggable: true, useHandCursor: true });

        // Metadata for InputManager
        container.char = char;
        container.isKey = true; // Identifies this as a spawner

        return container;
    }

    resize(width, height) {
        this.drawKeyboard();
    }
}