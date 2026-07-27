/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect, useRef } from 'react';
import { Calculator, Delete } from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';

export default function CalculatorDropdown() {
  const [isOpen, setIsOpen] = useState(false);
  const [expression, setExpression] = useState('');
  const [result, setResult] = useState('0');
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Close dropdown on clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  // Handle keyboard entries when open
  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      const key = e.key;
      if (/[0-9+\-*/.]/.test(key)) {
        e.preventDefault();
        handleInput(key);
      } else if (key === 'Enter' || key === '=') {
        e.preventDefault();
        handleCalculate();
      } else if (key === 'Backspace') {
        e.preventDefault();
        handleBackspace();
      } else if (key === 'Escape') {
        e.preventDefault();
        setIsOpen(false);
      } else if (key.toLowerCase() === 'c') {
        e.preventDefault();
        handleClear();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, expression]);

  const handleInput = (char: string) => {
    // Check if we are starting a new operation after an error/result
    if (result === 'Error' || expression === 'Error') {
      setExpression(char);
      setResult('0');
      return;
    }

    // Prevent multiple consecutive operator inputs
    const lastChar = expression.slice(-1);
    const isOperator = (c: string) => ['+', '-', '*', '/'].includes(c);

    if (isOperator(char) && (expression === '' || isOperator(lastChar))) {
      if (expression !== '' && isOperator(lastChar)) {
        // Replace last operator with the new one
        setExpression(expression.slice(0, -1) + char);
      }
      return;
    }

    setExpression((prev) => prev + char);
  };

  const handleClear = () => {
    setExpression('');
    setResult('0');
  };

  const handleBackspace = () => {
    setExpression((prev) => prev.slice(0, -1));
  };

  const evaluateExpression = (expr: string): string => {
    const sanitized = expr.replace(/\s+/g, '');
    if (!/^[0-9+\-*/.]*$/.test(sanitized)) {
      return 'Error';
    }
    try {
      // Safe eval pre-validated against malicious code injection
      const res = new Function(`return ${sanitized}`)();
      if (res === Infinity || isNaN(res)) return 'Error';
      // Format decimal points elegantly
      const formattedRes = Number(res.toFixed(8));
      return String(formattedRes);
    } catch {
      return 'Error';
    }
  };

  const handleCalculate = () => {
    if (!expression) return;
    const evalResult = evaluateExpression(expression);
    setResult(evalResult);
    // Keep expression but set it to result to allow chaining further calculations
    if (evalResult !== 'Error') {
      setExpression(evalResult);
    } else {
      setExpression('');
    }
  };

  return (
    <div className="relative" ref={dropdownRef}>
      {/* Calculator Header Icon Button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className={`p-2 rounded-xl border transition-all flex items-center justify-center cursor-pointer hover:bg-slate-100 ${
          isOpen
            ? 'bg-indigo-50 text-indigo-600 border-indigo-200'
            : 'bg-white border-slate-200 text-slate-500 hover:text-slate-700'
        }`}
        title="Open Calculator"
      >
        <Calculator size={16} />
      </button>

      {/* Calculator Dropdown Overlay */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, y: 8, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 8, scale: 0.95 }}
            transition={{ duration: 0.15 }}
            className="absolute right-0 mt-2 bg-white/95 backdrop-blur-md border border-slate-200 shadow-2xl rounded-3xl p-4 w-64 z-50 overflow-hidden"
          >
            {/* Display Screen */}
            <div className="bg-slate-50 border border-slate-100/80 rounded-2xl p-3 text-right font-mono mb-3 text-slate-800 flex flex-col justify-between min-h-[64px]">
              <div className="text-[10px] text-slate-400 truncate max-w-full font-bold select-none h-4">
                {expression || ' '}
              </div>
              <div className="text-lg font-black tracking-tight truncate max-w-full mt-1">
                {result !== '0' && expression === '' ? result : expression || result}
              </div>
            </div>

            {/* Keys Grid */}
            <div className="grid grid-cols-4 gap-2">
              {/* Clear & Backspace */}
              <button
                onClick={handleClear}
                className="col-span-2 py-2.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-xl text-xs font-black transition-colors cursor-pointer select-none"
              >
                CLEAR
              </button>
              <button
                onClick={handleBackspace}
                className="py-2.5 bg-slate-50 text-slate-500 hover:bg-slate-100 rounded-xl flex items-center justify-center transition-colors cursor-pointer select-none"
              >
                <Delete size={15} />
              </button>
              <button
                onClick={() => handleInput('/')}
                className="py-2.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-xl text-sm font-black transition-colors cursor-pointer select-none"
              >
                ÷
              </button>

              {/* Numbers & Operators */}
              {[
                { label: '7', action: () => handleInput('7'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '8', action: () => handleInput('8'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '9', action: () => handleInput('9'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '×', action: () => handleInput('*'), class: 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100' },

                { label: '4', action: () => handleInput('4'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '5', action: () => handleInput('5'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '6', action: () => handleInput('6'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '−', action: () => handleInput('-'), class: 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100' },

                { label: '1', action: () => handleInput('1'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '2', action: () => handleInput('2'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '3', action: () => handleInput('3'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
                { label: '+', action: () => handleInput('+'), class: 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100' },

                { label: '0', action: () => handleInput('0'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100 col-span-2' },
                { label: '.', action: () => handleInput('.'), class: 'bg-slate-50 text-slate-800 hover:bg-slate-100' },
              ].map((btn, idx) => (
                <button
                  key={idx}
                  onClick={btn.action}
                  className={`py-2.5 text-xs font-black rounded-xl transition-colors cursor-pointer select-none ${btn.class}`}
                >
                  {btn.label}
                </button>
              ))}

              {/* Equals Key */}
              <button
                onClick={handleCalculate}
                className="py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-black transition-colors cursor-pointer select-none shadow-md shadow-indigo-600/10 col-span-4 mt-1"
              >
                =
              </button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
