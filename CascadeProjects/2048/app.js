const { useState, useEffect, useCallback } = React;

const GRID_SIZE = 4;
const WINNING_VALUE = 2048;

const getTileColor = (value) => {
    const colors = {
        2: 'bg-amber-100 text-amber-800',
        4: 'bg-amber-200 text-amber-900',
        8: 'bg-orange-300 text-orange-900',
        16: 'bg-orange-400 text-white',
        32: 'bg-orange-500 text-white',
        64: 'bg-red-500 text-white',
        128: 'bg-yellow-400 text-white',
        256: 'bg-yellow-500 text-white',
        512: 'bg-yellow-600 text-white',
        1024: 'bg-amber-500 text-white',
        2048: 'bg-amber-600 text-white',
    };
    return colors[value] || 'bg-amber-700 text-white';
};

const createEmptyGrid = () => {
    return Array(GRID_SIZE).fill(null).map(() => Array(GRID_SIZE).fill(0));
};

const addRandomTile = (grid) => {
    const emptyCells = [];
    for (let r = 0; r < GRID_SIZE; r++) {
        for (let c = 0; c < GRID_SIZE; c++) {
            if (grid[r][c] === 0) {
                emptyCells.push({ r, c });
            }
        }
    }
    if (emptyCells.length === 0) return grid;
    
    const { r, c } = emptyCells[Math.floor(Math.random() * emptyCells.length)];
    const newGrid = grid.map(row => [...row]);
    newGrid[r][c] = Math.random() < 0.9 ? 2 : 4;
    return newGrid;
};

const moveLeft = (grid) => {
    const newGrid = grid.map(row => [...row]);
    let score = 0;
    let moved = false;
    
    for (let r = 0; r < GRID_SIZE; r++) {
        let row = newGrid[r].filter(val => val !== 0);
        for (let i = 0; i < row.length - 1; i++) {
            if (row[i] === row[i + 1]) {
                row[i] *= 2;
                score += row[i];
                row.splice(i + 1, 1);
            }
        }
        while (row.length < GRID_SIZE) {
            row.push(0);
        }
        newGrid[r] = row;
        if (newGrid[r].join(',') !== grid[r].join(',')) {
            moved = true;
        }
    }
    return { grid: newGrid, score, moved };
};

const rotateGrid = (grid) => {
    const newGrid = createEmptyGrid();
    for (let r = 0; r < GRID_SIZE; r++) {
        for (let c = 0; c < GRID_SIZE; c++) {
            newGrid[c][GRID_SIZE - 1 - r] = grid[r][c];
        }
    }
    return newGrid;
};

const move = (grid, direction) => {
    let result = { grid, score: 0, moved: false };
    let rotations = 0;
    
    switch (direction) {
        case 'left':
            rotations = 0;
            break;
        case 'up':
            rotations = 3;
            break;
        case 'right':
            rotations = 2;
            break;
        case 'down':
            rotations = 1;
            break;
    }
    
    for (let i = 0; i < rotations; i++) {
        result.grid = rotateGrid(result.grid);
    }
    
    result = moveLeft(result.grid);
    result.score = result.score;
    
    for (let i = 0; i < (4 - rotations) % 4; i++) {
        result.grid = rotateGrid(result.grid);
    }
    
    return result;
};

const canMove = (grid) => {
    for (let r = 0; r < GRID_SIZE; r++) {
        for (let c = 0; c < GRID_SIZE; c++) {
            if (grid[r][c] === 0) return true;
            if (c < GRID_SIZE - 1 && grid[r][c] === grid[r][c + 1]) return true;
            if (r < GRID_SIZE - 1 && grid[r][c] === grid[r + 1][c]) return true;
        }
    }
    return false;
};

const hasWon = (grid) => {
    for (let r = 0; r < GRID_SIZE; r++) {
        for (let c = 0; c < GRID_SIZE; c++) {
            if (grid[r][c] >= WINNING_VALUE) return true;
        }
    }
    return false;
};

const Tile = ({ value, isNew, isMerged }) => {
    const colorClass = getTileColor(value);
    const animationClass = isNew ? 'tile-new' : isMerged ? 'tile-merged' : '';
    
    return (
        <div className={`tile ${colorClass} ${animationClass} w-full h-full rounded-lg flex items-center justify-center font-bold text-2xl md:text-4xl shadow-md`}>
            {value}
        </div>
    );
};

const Game2048 = () => {
    const [grid, setGrid] = useState(() => {
        let initialGrid = createEmptyGrid();
        initialGrid = addRandomTile(initialGrid);
        initialGrid = addRandomTile(initialGrid);
        return initialGrid;
    });
    const [score, setScore] = useState(0);
    const [bestScore, setBestScore] = useState(0);
    const [gameOver, setGameOver] = useState(false);
    const [won, setWon] = useState(false);
    const [continuePlaying, setContinuePlaying] = useState(false);
    const [newTiles, setNewTiles] = useState(new Set());
    const [mergedTiles, setMergedTiles] = useState(new Set());

    useEffect(() => {
        const handleKeyDown = (e) => {
            if (gameOver || (won && !continuePlaying)) return;
            
            const keyMap = {
                'ArrowLeft': 'left',
                'ArrowRight': 'right',
                'ArrowUp': 'up',
                'ArrowDown': 'down',
            };
            
            if (keyMap[e.key]) {
                e.preventDefault();
                handleMove(keyMap[e.key]);
            }
        };
        
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [grid, gameOver, won, continuePlaying]);

    const handleMove = useCallback((direction) => {
        if (gameOver || (won && !continuePlaying)) return;
        
        const result = move(grid, direction);
        
        if (result.moved) {
            const newGrid = addRandomTile(result.grid);
            setGrid(newGrid);
            setScore(prev => prev + result.score);
            setBestScore(prev => Math.max(prev, score + result.score));
            
            // Track new tile position
            const newTilePositions = new Set();
            for (let r = 0; r < GRID_SIZE; r++) {
                for (let c = 0; c < GRID_SIZE; c++) {
                    if (newGrid[r][c] !== 0 && grid[r][c] === 0) {
                        newTilePositions.add(`${r}-${c}`);
                    }
                }
            }
            setNewTiles(newTilePositions);
            
            // Track merged tiles
            const mergedPositions = new Set();
            for (let r = 0; r < GRID_SIZE; r++) {
                for (let c = 0; c < GRID_SIZE; c++) {
                    if (newGrid[r][c] !== grid[r][c] && grid[r][c] !== 0) {
                        mergedPositions.add(`${r}-${c}`);
                    }
                }
            }
            setMergedTiles(mergedPositions);
            
            // Clear animation classes after animation
            setTimeout(() => {
                setNewTiles(new Set());
                setMergedTiles(new Set());
            }, 200);
            
            if (!won && hasWon(newGrid)) {
                setWon(true);
            } else if (!canMove(newGrid)) {
                setGameOver(true);
            }
        }
    }, [grid, gameOver, won, continuePlaying, score]);

    const handleTouchStart = (e) => {
        const touch = e.touches[0];
        setTouchStart({ x: touch.clientX, y: touch.clientY });
    };

    const handleTouchEnd = (e) => {
        if (!touchStart) return;
        
        const touch = e.changedTouches[0];
        const dx = touch.clientX - touchStart.x;
        const dy = touch.clientY - touchStart.y;
        
        const minSwipeDistance = 50;
        
        if (Math.abs(dx) > Math.abs(dy)) {
            if (Math.abs(dx) > minSwipeDistance) {
                handleMove(dx > 0 ? 'right' : 'left');
            }
        } else {
            if (Math.abs(dy) > minSwipeDistance) {
                handleMove(dy > 0 ? 'down' : 'up');
            }
        }
        
        setTouchStart(null);
    };

    const [touchStart, setTouchStart] = useState(null);

    const resetGame = () => {
        let newGrid = createEmptyGrid();
        newGrid = addRandomTile(newGrid);
        newGrid = addRandomTile(newGrid);
        setGrid(newGrid);
        setScore(0);
        setGameOver(false);
        setWon(false);
        setContinuePlaying(false);
        setNewTiles(new Set());
        setMergedTiles(new Set());
    };

    const handleContinue = () => {
        setContinuePlaying(true);
    };

    return (
        <div className="flex flex-col items-center gap-6 p-4">
            <div className="flex items-center justify-between w-full max-w-md">
                <h1 className="text-5xl font-bold text-amber-700">2048</h1>
                <div className="flex gap-2">
                    <div className="bg-amber-600 text-white px-4 py-2 rounded-lg text-center min-w-20">
                        <div className="text-xs uppercase opacity-80">Score</div>
                        <div className="text-xl font-bold">{score}</div>
                    </div>
                    <div className="bg-amber-700 text-white px-4 py-2 rounded-lg text-center min-w-20">
                        <div className="text-xs uppercase opacity-80">Best</div>
                        <div className="text-xl font-bold">{bestScore}</div>
                    </div>
                </div>
            </div>
            
            <div className="flex gap-2">
                <button 
                    onClick={resetGame}
                    className="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2 px-4 rounded-lg transition-colors"
                >
                    New Game
                </button>
            </div>
            
            <div 
                className="bg-amber-800 p-3 rounded-xl relative"
                style={{ width: 'min(90vw, 400px)', aspectRatio: '1' }}
                onTouchStart={handleTouchStart}
                onTouchEnd={handleTouchEnd}
            >
                <div className="grid grid-cols-4 gap-2 h-full">
                    {grid.map((row, r) =>
                        row.map((value, c) => (
                            <div
                                key={`${r}-${c}`}
                                className="bg-amber-900/50 rounded-lg"
                            >
                                {value !== 0 && (
                                    <Tile
                                        value={value}
                                        isNew={newTiles.has(`${r}-${c}`)}
                                        isMerged={mergedTiles.has(`${r}-${c}`)}
                                    />
                                )}
                            </div>
                        ))
                    )}
                </div>
                
                {gameOver && (
                    <div className="game-over-overlay absolute inset-0 bg-white/80 flex flex-col items-center justify-center rounded-xl">
                        <h2 className="text-4xl font-bold text-amber-800 mb-4">Game Over!</h2>
                        <button 
                            onClick={resetGame}
                            className="bg-amber-600 hover:bg-amber-700 text-white font-bold py-3 px-6 rounded-lg transition-colors"
                        >
                            Try Again
                        </button>
                    </div>
                )}
                
                {won && !continuePlaying && (
                    <div className="game-over-overlay absolute inset-0 bg-amber-400/80 flex flex-col items-center justify-center rounded-xl">
                        <h2 className="text-4xl font-bold text-white mb-2">You Win!</h2>
                        <p className="text-white mb-4">You reached 2048!</p>
                        <div className="flex gap-2">
                            <button 
                                onClick={handleContinue}
                                className="bg-amber-700 hover:bg-amber-800 text-white font-bold py-3 px-6 rounded-lg transition-colors"
                            >
                                Keep Playing
                            </button>
                            <button 
                                onClick={resetGame}
                                className="bg-white hover:bg-gray-100 text-amber-700 font-bold py-3 px-6 rounded-lg transition-colors"
                            >
                                New Game
                            </button>
                        </div>
                    </div>
                )}
            </div>
            
            <div className="text-center text-gray-600 text-sm">
                <p className="font-semibold mb-1">How to play:</p>
                <p>Use arrow keys or swipe to move tiles.</p>
                <p>Merge same numbers to reach 2048!</p>
            </div>
        </div>
    );
};

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<Game2048 />);
