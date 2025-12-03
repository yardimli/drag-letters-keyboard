/**
 * Manages the visual keyboard at the bottom using 3D balls.
 */
export class KeyboardManager {
    constructor (scene) {
        this.scene = scene;
        this.keys = [];
        this.keyGroup = this.scene.add.container();

        // Configuration
        this.letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split('');
        this.keySize = 60; // Size of the ball
        this.padding = 10;
    }

    create () {
        this.drawKeyboard();
    }

    drawKeyboard () {
        this.keyGroup.removeAll(true);
        this.keys = [];

        const screenWidth = this.scene.scale.width;
        const screenHeight = this.scene.scale.height;

        // --- Responsive Layout Logic ---
        const itemWidth = this.keySize + this.padding;
        const availableWidth = screenWidth - (this.padding * 2);

        // Calculate how many columns fit on screen
        let maxCols = Math.floor(availableWidth / itemWidth);

        // Constraints
        if (maxCols > 10) maxCols = 10; // Maximum 10 columns on desktop
        if (maxCols < 3) maxCols = 3;   // Minimum 3 columns on very small screens

        // Calculate total rows needed
        const totalRows = Math.ceil(this.letters.length / maxCols);

        // Center the grid horizontally
        const totalGridWidth = (maxCols * this.keySize) + ((maxCols - 1) * this.padding);
        const startX = (screenWidth - totalGridWidth) / 2 + (this.keySize / 2);

        // Position at bottom, adjusting for number of rows
        const gridHeight = (totalRows * this.keySize) + ((totalRows - 1) * this.padding);
        const startY = screenHeight - gridHeight - 50; // 50px padding from bottom

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

    createKeyBall (x, y, char) {
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
        container.setInteractive({ draggable: true, useHandCursor: true });

        // Metadata for InputManager
        container.char = char;
        container.isKey = true; // Identifies this as a spawner

        return container;
    }

    /**
     * Updates the visual state and interactivity of keys based on valid next characters.
     * @param {Array<string>} validChars - List of characters that are allowed next.
     */
    updateKeyAvailability (validChars) {
        this.keys.forEach(keyContainer => {
            const char = keyContainer.char;
            // Check if this character is in the allowed list
            const isValid = validChars.includes(char);

            if (isValid) {
                keyContainer.setAlpha(1);
                keyContainer.setInteractive(); // Re-enable interaction
                const ball = keyContainer.list[0];
                if (ball) ball.setTint(0x0077ff); // Restore blue color
            } else {
                keyContainer.setAlpha(0.3);
                keyContainer.disableInteractive(); // Disable interaction (cannot drag)
                const ball = keyContainer.list[0];
                if (ball) ball.setTint(0x555555); // Grey out
            }
        });
    }

    resize (width, height) {
        this.drawKeyboard();
        // Note: After resize, keys are recreated, so GameScene needs to re-apply availability.
        // This is handled by the GameScene calling updateGameFlow() after resize usually.
    }
}