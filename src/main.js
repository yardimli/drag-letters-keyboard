import { GameScene } from './scenes/GameScene.js';

/**
 * Launches the game with the specific language configuration.
 * @param {string} language - The language code selected by the user (e.g., 'en', 'zh').
 * @param {Array<string>} categories - List of selected categories.
 */
export async function launchGame(language, categories = []) {
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
        scene: [GameScene]
    };
    
    const game = new Phaser.Game(config);
    
    // --- Store Data in Registry ---
    // We store the full list, but we also pass the language setting to the scene
    game.registry.set('dictionary', allWords);
    game.registry.set('language', language);
    game.registry.set('categories', categories);
    
    return game;
}
