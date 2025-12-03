import { GameScene } from './scenes/GameScene.js';

// MODIFIED: Simplified to load data and launch GameScene directly
export async function launchGame() {
    let allWords = [];

    try {
        const response = await fetch('assets/words.json');
        const data = await response.json();

        // Store all words for dictionary lookup
        if (data && data.words) {
            allWords = data.words;
        }

        console.log(`Dictionary Loaded: ${allWords.length} words.`);

    } catch (error) {
        console.error("Failed to initialize game data:", error);
    }

    // --- Game Config ---
    const config = {
        type: Phaser.AUTO,
        width: window.innerWidth,
        height: window.innerHeight,
        backgroundColor: '#2d2d2d',
        scale: {
            mode: Phaser.Scale.RESIZE,
            autoCenter: Phaser.Scale.CENTER_BOTH
        },
        physics: {
            default: 'arcade',
            arcade: {
                gravity: { y: 0 },
                debug: false
            }
        },
        // MODIFIED: Only GameScene is needed now
        scene: [GameScene]
    };

    const game = new Phaser.Game(config);

    // --- Store Data in Registry ---
    // We store the full list of words to check against user input
    game.registry.set('dictionary', allWords);

    return game;
}