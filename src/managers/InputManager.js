/**
 * Handles dragging logic using physics velocity.
 * Spawns clones when dragging from the keyboard.
 */
export class InputManager {
    constructor(scene) {
        this.scene = scene;
        this.customCursor = null;
        this.draggedObject = null;
        this.currentPointer = null;
    }

    create() {
        // --- Custom Cursor Setup ---
        this.scene.input.setDefaultCursor('none');

        if (!this.scene.textures.exists('customCursorTexture')) {
            const cursorSize = 32;
            const cursorGraphics = this.scene.make.graphics();
            cursorGraphics.lineStyle(2, 0xFFFFFF, 1);
            cursorGraphics.moveTo(cursorSize / 2, 0);
            cursorGraphics.lineTo(cursorSize / 2, cursorSize);
            cursorGraphics.moveTo(0, cursorSize / 2);
            cursorGraphics.lineTo(cursorSize, cursorSize / 2);
            cursorGraphics.strokePath();
            cursorGraphics.generateTexture('customCursorTexture', cursorSize, cursorSize);
            cursorGraphics.destroy();
        }

        this.customCursor = this.scene.add.image(0, 0, 'customCursorTexture');
        this.customCursor.setDepth(2000);

        this.setupInputListeners();
    }

    setupInputListeners() {
        // Update custom cursor position
        this.scene.input.on('pointermove', (pointer) => {
            this.customCursor.x = pointer.x;
            this.customCursor.y = pointer.y;
        });

        this.scene.input.on('dragstart', (pointer, gameObject) => {
            this.scene.sound.play('click');
            this.currentPointer = pointer;

            // Case A: Dragging from Keyboard (Spawn new ball)
            if (gameObject.isKey) {
                // Create a dynamic ball clone at the key's position
                const ball = this.spawnBall(pointer.x, pointer.y, gameObject.char);

                // Set origin for "bounce back" logic
                ball.originX = gameObject.x;
                ball.originY = gameObject.y;

                this.draggedObject = ball;
            }
            // Case B: Dragging an existing ball (e.g. from input area, if we allowed it)
            // For now, input area balls are clicked to pop, not dragged.
        });

        this.scene.input.on('drag', (pointer, gameObject, dragX, dragY) => {
            // Physics updates happen in update() loop
        });

        this.scene.input.on('dragend', (pointer, gameObject) => {
            if (this.draggedObject) {
                // Stop physics
                if (this.draggedObject.body) {
                    this.draggedObject.body.setVelocity(0, 0);
                }

                // Handle Drop
                this.scene.handleBallDrop(this.draggedObject);

                // Cleanup
                this.draggedObject = null;
                this.currentPointer = null;
            }
        });
    }

    spawnBall(x, y, char) {
        const container = this.scene.add.container(x, y);
        const size = 60;

        // Visuals
        const ballImage = this.scene.add.image(0, 0, 'ball3d');
        ballImage.setTint(0x44aaff); // Lighter blue while dragging
        ballImage.setDisplaySize(size, size);

        const text = this.scene.add.text(0, 0, char, {
            fontSize: '32px',
            fontStyle: 'bold',
            color: '#ffffff'
        }).setOrigin(0.5);

        container.add([ballImage, text]);
        container.setSize(size, size);
        container.char = char;
        container.setDepth(1000);

        // Add Physics for the dragging effect
        this.scene.physics.add.existing(container);
        container.body.setCircle(size / 2);
        container.body.setDrag(100); // Slight drag

        return container;
    }

    update() {
        // Physics-based Dragging Logic (P-Controller)
        if (this.draggedObject && this.draggedObject.body && this.currentPointer) {
            const speed = 10;
            const maxVelocity = 1000;

            let vX = (this.currentPointer.x - this.draggedObject.x) * speed;
            let vY = (this.currentPointer.y - this.draggedObject.y) * speed;

            // Clamp velocity
            vX = Phaser.Math.Clamp(vX, -maxVelocity, maxVelocity);
            vY = Phaser.Math.Clamp(vY, -maxVelocity, maxVelocity);

            this.draggedObject.body.setVelocity(vX, vY);
        }
    }
}