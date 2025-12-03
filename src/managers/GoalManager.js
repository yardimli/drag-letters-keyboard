export class GoalManager {
    constructor(scene) {
        this.scene = scene;
        this.goals = [];
        this.walls = this.scene.physics.add.staticGroup();
        this.bottomWalls = this.scene.physics.add.staticGroup();
        this.glitchTimers = [];

        // Settings
        this.goalSize = 70;
        this.wallThickness = 8;
        this.wallColor = 0x222222; // Darker wall color to blend with gradient
    }

    /**
     * Creates the goal containers and physical walls for the given word.
     * @param {string} word
     */
    createGoals(word) {
        const startX = 100;
        const startY = 140;
        const spacing = 110;

        const totalWidth = (word.length * spacing);
        const screenCenter = this.scene.scale.width / 2;
        const startOffset = screenCenter - (totalWidth / 2) + (spacing / 2);

        for (let i = 0; i < word.length; i++) {
            const x = startOffset + (i * spacing);
            const y = startY;

            // 1. Create the Visual Goal Container
            const goalContainer = this.scene.add.container(x, y);

            // MODIFIED: Create a more colorful, game-like visual
            const graphics = this.scene.add.graphics();

            // Outer Glow / Stroke
            graphics.lineStyle(4, 0x00ffff, 1); // Cyan Neon Stroke
            graphics.strokeRoundedRect(-this.goalSize / 2, -this.goalSize / 2, this.goalSize, this.goalSize, 15);

            // Inner Fill (Semi-transparent)
            graphics.fillStyle(0x00ffff, 0.1);
            graphics.fillRoundedRect(-this.goalSize / 2, -this.goalSize / 2, this.goalSize, this.goalSize, 15);

            // Add a "Tech" detail - small corners
            graphics.lineStyle(2, 0xffffff, 0.8);
            const s = this.goalSize / 2 - 5;
            // Top Left
            graphics.beginPath(); graphics.moveTo(-s, -s + 10); graphics.lineTo(-s, -s); graphics.lineTo(-s + 10, -s); graphics.strokePath();
            // Bottom Right
            graphics.beginPath(); graphics.moveTo(s, s - 10); graphics.lineTo(s, s); graphics.lineTo(s - 10, s); graphics.strokePath();

            // Expected Character Text
            const text = this.scene.add.text(0, 0, word[i], {
                fontSize: '48px',
                color: '#00ffff', // Match the neon look
                fontFamily: 'Arial',
                fontStyle: 'bold'
            }).setOrigin(0.5).setAlpha(0);

            // Add graphics and text to container
            goalContainer.add([graphics, text]);

            // Store metadata
            goalContainer.expectedChar = word[i];
            goalContainer.isFilled = false;

            this.goals.push(goalContainer);

            // 2. Create Visible Physical Walls (Top, Left, Right)
            const halfSize = this.goalSize / 2;
            const halfThick = this.wallThickness / 2;

            // Top Wall
            const topWall = this.scene.add.rectangle(
                x,
                y - halfSize - halfThick,
                this.goalSize + (this.wallThickness * 2),
                this.wallThickness,
                this.wallColor
            );
            this.scene.physics.add.existing(topWall, true);
            this.walls.add(topWall);

            // Left Wall
            const leftWall = this.scene.add.rectangle(
                x - halfSize - halfThick,
                y,
                this.wallThickness,
                this.goalSize,
                this.wallColor
            );
            this.scene.physics.add.existing(leftWall, true);
            this.walls.add(leftWall);

            // Right Wall
            const rightWall = this.scene.add.rectangle(
                x + halfSize + halfThick,
                y,
                this.wallThickness,
                this.goalSize,
                this.wallColor
            );
            this.scene.physics.add.existing(rightWall, true);
            this.walls.add(rightWall);

            // Bottom Wall (Initially visible)
            const bottomWall = this.scene.add.rectangle(
                x,
                y + halfSize + halfThick,
                this.goalSize + (this.wallThickness * 2),
                this.wallThickness,
                this.wallColor
            );
            this.scene.physics.add.existing(bottomWall, true);
            this.bottomWalls.add(bottomWall);
        }
    }

    startGlitchSequence() {
        // Get the text objects (index 1 in container children)
        const texts = this.goals.map(goal => goal.list[1]);
        const shuffledTexts = Phaser.Utils.Array.Shuffle([...texts]);

        shuffledTexts.forEach((textObj, index) => {
            const delay = 500 + (index * 300);
            const timer = this.scene.time.delayedCall(delay, () => {
                if (textObj.active) {
                    this.runGlitchInAndOut(textObj);
                }
            });
            this.glitchTimers.push(timer);
        });
    }

    runGlitchInAndOut(textObj) {
        this.scene.tweens.add({
            targets: textObj,
            alpha: { from: 0, to: 1 },
            scaleX: { from: 0.1, to: 1 },
            scaleY: { from: 2, to: 1 },
            duration: 200,
            ease: 'Bounce.Out',
            onComplete: () => {
                this.scene.tweens.add({
                    targets: textObj,
                    x: { from: textObj.x, to: textObj.x + Phaser.Math.Between(-2, 2) },
                    y: { from: textObj.y, to: textObj.y + Phaser.Math.Between(-2, 2) },
                    duration: 50,
                    yoyo: true,
                    repeat: 10,
                    onComplete: () => {
                        this.runGlitchOut(textObj);
                    }
                });
            }
        });
    }

    runGlitchOut(textObj) {
        this.scene.tweens.add({
            targets: textObj,
            y: -1000,
            scaleX: 0.1,
            scaleY: 2,
            alpha: 0,
            delay:1500,
            duration: 200,
            ease: 'Power1'
        });
    }

    getGoals() {
        return this.goals;
    }

    getWallGroup() {
        return this.walls;
    }

    getBottomWallGroup() {
        return this.bottomWalls;
    }

    toggleBottomWalls(enabled) {
        this.bottomWalls.children.iterate((wall) => {
            if (wall && wall.body) {
                wall.setVisible(enabled);
                wall.body.enable = enabled;
            }
        });
    }

    clear() {
        if (this.glitchTimers) {
            this.glitchTimers.forEach(timer => timer.remove(false));
        }
        this.glitchTimers = [];

        this.goals.forEach(goal => goal.destroy());
        this.goals = [];

        this.walls.clear(true, true);
        this.bottomWalls.clear(true, true);
    }

    resize(width, height, totalLetters) {
        // Handled by level reset
    }
}