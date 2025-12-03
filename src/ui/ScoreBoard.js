export class ScoreBoard {
    constructor(scene) {
        this.scene = scene;

        // --- Settings ---
        this.config = {
            topBarHeight: 50,
            rightBarWidth: 40,

            // Cell Settings
            cellGap: 4,
            topBarCellWidth: 15,   // Width of cells in the top progress bar
            rightBarCellHeight: 15, // Height of cells in the right accuracy bar

            // Colors
            emptyColor: 0x222222,
            borderColor: 0x000000,
            filledColorProgress: 0x00ff00,
            filledColorAccuracyHigh: 0x00ccff,
            filledColorAccuracyMed: 0xffaa00,
            filledColorAccuracyLow: 0xff0000
        };

        // --- State ---
        // We use an object to hold values so we can tween them
        this.state = {
            progressPct: 0,
            accuracyPct: 1
        };

        // Visual Elements
        this.graphics = null;
        this.progressText = null;
        this.accuracyText = null;
    }

    create() {
        // Create the Graphics object that will draw all cells
        this.graphics = this.scene.add.graphics();
        this.graphics.setDepth(100);

        // --- Text Labels ---
        const textStyle = {
            fontSize: '20px',
            fontFamily: 'Arial',
            fontStyle: 'bold',
            color: '#ffffff',
            stroke: '#000000',
            strokeThickness: 4
        };

        // Progress Text (Top Center)
        this.progressText = this.scene.add.text(0, 0, "0%", textStyle).setOrigin(0.5, 0.5);
        this.progressText.setDepth(101);

        // Accuracy Text (Right Center, Rotated)
        this.accuracyText = this.scene.add.text(0, 0, "100%", textStyle).setOrigin(0.5, 0.5);
        this.accuracyText.setAngle(-90);
        this.accuracyText.setDepth(101);

        // Initial Render
        this.render();
    }

    /**
     * Updates the scoreboard with new game data.
     * Triggers animations for the bars.
     *
     * MODIFIED: Now calculates progress based on Global Session Totals.
     *
     * @param {number} sessionCorrect - Total correct letters placed in session
     * @param {number} sessionTotal - Total letters expected in the entire session
     * @param {number} sessionWrong - Total wrong drops in session
     */
    update(sessionCorrect, sessionTotal, sessionWrong) {
        // 1. Calculate Targets

        // Progress: Percentage of the entire game completed
        const targetProgress = sessionTotal > 0 ? (sessionCorrect / sessionTotal) : 0;

        // Accuracy: Correct drops vs Total attempts
        const totalAttempts = sessionCorrect + sessionWrong;
        const targetAccuracy = totalAttempts > 0 ? (sessionCorrect / totalAttempts) : 1;

        // 2. Tween State values to Targets
        this.scene.tweens.add({
            targets: this.state,
            progressPct: targetProgress,
            accuracyPct: targetAccuracy,
            duration: 500,
            ease: 'Power2',
            onUpdate: () => {
                this.render();
            }
        });
    }

    /**
     * Renders the bars based on current this.state values.
     * Called during tween updates and resize events.
     */
    render() {
        const width = this.scene.scale.width;
        const height = this.scene.scale.height;
        const {
            topBarHeight, rightBarWidth, cellGap,
            topBarCellWidth, rightBarCellHeight,
            emptyColor, filledColorProgress,
            filledColorAccuracyHigh, filledColorAccuracyMed, filledColorAccuracyLow
        } = this.config;

        this.graphics.clear();

        // ============================================================
        // 1. Top Progress Bar (Horizontal Cells)
        // ============================================================

        // Dimensions
        const topBarWidth = width; // Full width
        const availableWidth = topBarWidth - (cellGap * 2); // Padding
        const numTopCells = Math.floor(availableWidth / (topBarCellWidth + cellGap));

        // Calculate how many cells should be filled based on current animated percentage
        const filledTopCells = Math.floor(numTopCells * this.state.progressPct);

        // Draw Cells
        for (let i = 0; i < numTopCells; i++) {
            const x = cellGap + (i * (topBarCellWidth + cellGap));
            const y = cellGap;
            const cellH = topBarHeight - (cellGap * 2);

            // Determine Color
            const isFilled = i < filledTopCells;
            const color = isFilled ? filledColorProgress : emptyColor;
            const alpha = isFilled ? 1 : 0.5;

            this.graphics.fillStyle(color, alpha);

            // Add a slight stroke for definition
            if (isFilled) {
                this.graphics.lineStyle(1, 0xffffff, 0.5);
                this.graphics.fillRect(x, y, topBarCellWidth, cellH);
                this.graphics.strokeRect(x, y, topBarCellWidth, cellH);
            } else {
                this.graphics.fillRect(x, y, topBarCellWidth, cellH);
            }
        }

        // Update Progress Text Position & Content
        this.progressText.setPosition(width / 2, topBarHeight / 2);
        this.progressText.setText(`${Math.floor(this.state.progressPct * 100)}%`);


        // ============================================================
        // 2. Right Accuracy Bar (Vertical Cells)
        // ============================================================

        // Dimensions
        // Starts below the top bar
        const rightBarX = width - rightBarWidth;
        const rightBarY = topBarHeight;
        const rightBarH = height - topBarHeight;

        const availableHeight = rightBarH - (cellGap * 2);
        const numRightCells = Math.floor(availableHeight / (rightBarCellHeight + cellGap));

        // Calculate filled cells
        const filledRightCells = Math.floor(numRightCells * this.state.accuracyPct);

        // Determine Accuracy Color
        let accColor = filledColorAccuracyHigh;
        if (this.state.accuracyPct < 0.5) accColor = filledColorAccuracyLow;
        else if (this.state.accuracyPct < 0.8) accColor = filledColorAccuracyMed;

        // Draw Cells (Bottom to Top)
        for (let i = 0; i < numRightCells; i++) {
            // Calculate Y from bottom up
            // i=0 is bottom-most cell
            const cellIndexFromBottom = i;

            const x = rightBarX + cellGap;
            const cellW = rightBarWidth - (cellGap * 2);

            // Position: Start at bottom of area, move up
            const y = (height - cellGap - rightBarCellHeight) - (i * (rightBarCellHeight + cellGap));

            // Determine Color
            const isFilled = i < filledRightCells;
            const color = isFilled ? accColor : emptyColor;
            const alpha = isFilled ? 1 : 0.5;

            this.graphics.fillStyle(color, alpha);

            if (isFilled) {
                this.graphics.lineStyle(1, 0xffffff, 0.5);
                this.graphics.fillRect(x, y, cellW, rightBarCellHeight);
                this.graphics.strokeRect(x, y, cellW, rightBarCellHeight);
            } else {
                this.graphics.fillRect(x, y, cellW, rightBarCellHeight);
            }
        }

        // Update Accuracy Text Position & Content
        this.accuracyText.setPosition(width - (rightBarWidth / 2), topBarHeight + (rightBarH / 2));
        this.accuracyText.setText(`${Math.floor(this.state.accuracyPct * 100)}%`);
    }

    reset() {
        // Reset state
        this.state.progressPct = 0;
        this.state.accuracyPct = 1;

        // Force immediate render
        this.render();
    }

    resize(width, height) {
        // Just re-render with new dimensions
        this.render();
    }
}