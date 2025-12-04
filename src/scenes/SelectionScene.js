export class SelectionScene extends Phaser.Scene {
    constructor () {
        super({ key: 'SelectionScene' });
        this.customCursor = null;
    }
    
    init () {
        this.settings = this.registry.get('gameSettings') || { lang: 'en', count: 3 };
        this.completedWords = this.registry.get('completedWords') || [];
        this.wordsToDisplay = [];
    }
    
    preload () {
        this.load.json('wordData', 'assets/words.json');
        this.createParticleTexture();
    }
    
    create () {
        this.createBackground();
        this.createCustomCursor();
        
        const data = this.cache.json.get('wordData');
        
        if (!this.registry.get('sessionWords')) {
            let availableWords = data.words.filter(w => w.lang === this.settings.lang);
            
            if (availableWords.length === 0) {
                console.warn(`No words found for language: ${this.settings.lang}. Loading all words.`);
                availableWords = [...data.words];
            }
            
            const shuffled = Phaser.Utils.Array.Shuffle(availableWords);
            this.wordsToDisplay = shuffled.slice(0, parseInt(this.settings.count, 10));
            this.registry.set('sessionWords', this.wordsToDisplay);
        } else {
            this.wordsToDisplay = this.registry.get('sessionWords');
        }
        
        let assetsToLoad = 0;
        
        this.wordsToDisplay.forEach((word) => {
            // Prefer thumb for grid, fallback to image
            const imgSrc = word.thumb || word.image;
            if (imgSrc && !this.textures.exists(word.text)) {
                this.load.image(word.text, imgSrc);
                assetsToLoad++;
            }
        });
        
        if (assetsToLoad > 0) {
            this.add.text(this.scale.width / 2, this.scale.height / 2, 'Loading...', { fontSize: '32px', color: '#fff' }).setOrigin(0.5);
            this.load.once('complete', () => {
                this.children.removeAll();
                this.createBackground();
                this.createCustomCursor();
                this.buildGrid();
                this.checkWinCondition();
            });
            this.load.start();
        } else {
            this.buildGrid();
            this.checkWinCondition();
        }
        
        this.cameras.main.fadeIn(500, 0, 0, 0);
    }
    
    createCustomCursor () {
        this.input.setDefaultCursor('none');
        
        if (!this.textures.exists('customCursorTexture')) {
            const cursorSize = 32;
            const cursorGraphics = this.make.graphics();
            cursorGraphics.lineStyle(2, 0xFFFFFF, 1);
            cursorGraphics.moveTo(cursorSize / 2, 0);
            cursorGraphics.lineTo(cursorSize / 2, cursorSize);
            cursorGraphics.moveTo(0, cursorSize / 2);
            cursorGraphics.lineTo(cursorSize, cursorSize / 2);
            cursorGraphics.strokePath();
            cursorGraphics.generateTexture('customCursorTexture', cursorSize, cursorSize);
            cursorGraphics.destroy();
        }
        
        if (this.customCursor) this.customCursor.destroy();
        this.customCursor = this.add.image(0, 0, 'customCursorTexture');
        this.customCursor.setDepth(2000);
        this.customCursor.setVisible(false);
        
        this.input.on('pointermove', (pointer) => {
            this.customCursor.setVisible(true);
            this.customCursor.x = pointer.x;
            this.customCursor.y = pointer.y;
        });
    }
    
    createBackground () {
        const width = this.scale.width;
        const height = this.scale.height;
        this.bgGraphics = this.add.graphics();
        this.bgGraphics.fillGradientStyle(0x1a2a6c, 0xb21f1f, 0x000000, 0x000000, 1);
        this.bgGraphics.fillRect(0, 0, width, height);
        this.bgGraphics.setDepth(-100);
    }
    
    createParticleTexture () {
        if (this.textures.exists('firework')) return;
        const size = 8;
        const texture = this.textures.createCanvas('firework', size, size);
        const context = texture.getContext();
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, size, size);
        texture.refresh();
    }
    
    buildGrid () {
        const cols = 5;
        const cellWidth = 220;
        const cellHeight = 220;
        const spacing = 20;
        
        const gridWidth = (cols * cellWidth) + ((cols - 1) * spacing);
        const startX = (this.scale.width - gridWidth) / 2 + (cellWidth / 2);
        const startY = 200;
        
        this.add.text(this.scale.width / 2, 50, 'Select a Word', {
            fontSize: '48px',
            fontStyle: 'bold',
            color: '#00ffff',
            stroke: '#000000',
            strokeThickness: 4
        }).setOrigin(0.5);
        
        this.wordsToDisplay.forEach((wordObj, index) => {
            const col = index % cols;
            const row = Math.floor(index / cols);
            
            const x = startX + (col * (cellWidth + spacing));
            const y = startY + (row * (cellHeight + spacing));
            
            this.createCard(x, y, wordObj);
        });
    }
    
    createCard (x, y, wordObj) {
        const isCompleted = this.completedWords.includes(wordObj.text);
        const container = this.add.container(x, y);
        
        const bg = this.add.rectangle(0, 0, 200, 200, 0x333333);
        bg.setStrokeStyle(2, 0x00ffff);
        
        let image;
        if (this.textures.exists(wordObj.text)) {
            image = this.add.image(0, -20, wordObj.text);
            const scale = 140 / Math.max(image.width, image.height);
            image.setScale(scale);
        } else {
            image = this.add.text(0, -20, '?', { fontSize: '64px' }).setOrigin(0.5);
        }
        
        const text = this.add.text(0, 70, wordObj.text, {
            fontSize: '24px',
            fontStyle: 'bold',
            color: '#ffffff'
        }).setOrigin(0.5);
        
        container.add([bg, image, text]);
        
        if (isCompleted) {
            bg.setFillStyle(0x111111);
            bg.setStrokeStyle(2, 0x555555);
            image.setAlpha(0.3);
            text.setAlpha(0.3);
            const check = this.add.text(0, 0, '✔', { fontSize: '100px', color: '#00ff00' }).setOrigin(0.5);
            container.add(check);
        } else {
            bg.setInteractive();
            bg.on('pointerover', () => {
                bg.setFillStyle(0x444444);
                this.tweens.add({ targets: container, scale: 1.05, duration: 100 });
            });
            bg.on('pointerout', () => {
                bg.setFillStyle(0x333333);
                this.tweens.add({ targets: container, scale: 1, duration: 100 });
            });
            bg.on('pointerdown', () => {
                this.selectWord(wordObj);
            });
        }
    }
    
    selectWord (wordObj) {
        this.cameras.main.fadeOut(500, 0, 0, 0);
        this.cameras.main.once(Phaser.Cameras.Scene2D.Events.FADE_OUT_COMPLETE, () => {
            this.scene.start('GameScene', { wordObj: wordObj });
        });
    }
    
    checkWinCondition () {
        if (this.completedWords.length === this.wordsToDisplay.length && this.wordsToDisplay.length > 0) {
            this.triggerGameOver();
        }
    }
    
    triggerGameOver () {
        const gameOverText = this.add.text(this.scale.width / 2, this.scale.height / 2, 'GAME OVER', {
            fontSize: '120px',
            fontStyle: 'bold',
            color: '#ffffff',
            stroke: '#000000',
            strokeThickness: 8
        }).setOrigin(0.5).setDepth(1000);
        
        this.tweens.add({
            targets: gameOverText,
            alpha: 0,
            duration: 500,
            yoyo: true,
            repeat: -1
        });
        
        this.time.addEvent({
            delay: 200,
            loop: true,
            callback: () => {
                const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#00ffff', '#ff00ff'];
                gameOverText.setColor(Phaser.Utils.Array.GetRandom(colors));
            }
        });
        
        const particles = this.add.particles(0, 0, 'firework', {
            speed: { min: 100, max: 300 },
            angle: { min: 0, max: 360 },
            scale: { start: 1, end: 0 },
            blendMode: 'ADD',
            gravityY: 200,
            lifespan: 1000,
            emitting: false
        });
        
        this.time.addEvent({
            delay: 500,
            loop: true,
            callback: () => {
                const x = Phaser.Math.Between(100, this.scale.width - 100);
                const y = Phaser.Math.Between(100, this.scale.height - 100);
                const color = Phaser.Utils.Array.GetRandom([0xff0000, 0x00ff00, 0x0000ff, 0xffff00, 0xff00ff]);
                particles.setParticleTint(color);
                particles.emitParticleAt(x, y, 50);
                if (this.sound.get('bounce1')) {
                    this.sound.play('bounce1', { rate: 2.0, volume: 0.3 });
                }
            }
        });
    }
}
