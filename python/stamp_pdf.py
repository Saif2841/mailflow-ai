#!/usr/bin/env python3
"""
PDF Stamping Script for AI Email Automation System
Stamps approved invoices with approval details using PyMuPDF
"""

import fitz  # PyMuPDF
import os
import sys
from datetime import datetime
from typing import Optional


def stamp_pdf(
    input_path: str,
    output_path: str,
    approver_name: str,
    approval_date: str,
    status: str = "APPROVED",
    comments: Optional[str] = None
) -> bool:
    """
    Stamp a PDF with approval information
    
    Args:
        input_path: Path to input PDF file
        output_path: Path to save stamped PDF
        approver_name: Name of the approver
        approval_date: Date of approval (YYYY-MM-DD)
        status: Approval status (APPROVED/REJECTED)
        comments: Optional approval comments
    
    Returns:
        True if successful, False otherwise
    """
    try:
        # Open the PDF
        doc = fitz.open(input_path)
        
        # Define stamp text
        stamp_text = f"STATUS: {status}\n"
        stamp_text += f"APPROVED BY: {approver_name}\n"
        stamp_text += f"DATE: {approval_date}"
        
        if comments:
            stamp_text += f"\nCOMMENTS: {comments}"
        
        # Add stamp to first page
        page = doc[0]
        
        # Define stamp rectangle (top-right corner)
        rect = fitz.Rect(400, 50, 550, 150)
        
        # Create stamp appearance
        if status == "APPROVED":
            color = (0, 0.5, 0)  # Green
        else:
            color = (0.8, 0, 0)  # Red
        
        # Draw stamp
        page.draw_rect(rect, color=color, width=2)
        
        # Add text
        point = fitz.Point(410, 70)
        page.insert_text(point, stamp_text, fontsize=10, color=color)
        
        # Save the PDF
        doc.save(output_path)
        doc.close()
        
        print(f"Successfully stamped PDF: {output_path}")
        return True
        
    except Exception as e:
        print(f"Error stamping PDF: {str(e)}")
        return False


def main():
    """Main entry point for command-line usage"""
    if len(sys.argv) < 5:
        print("Usage: python stamp_pdf.py <input_path> <output_path> <approver_name> <approval_date> [status] [comments]")
        print("Example: python stamp_pdf.py input.pdf output.pdf 'John Doe' '2024-01-15' APPROVED 'Approved for payment'")
        sys.exit(1)
    
    input_path = sys.argv[1]
    output_path = sys.argv[2]
    approver_name = sys.argv[3]
    approval_date = sys.argv[4]
    status = sys.argv[5] if len(sys.argv) > 5 else "APPROVED"
    comments = sys.argv[6] if len(sys.argv) > 6 else None
    
    success = stamp_pdf(input_path, output_path, approver_name, approval_date, status, comments)
    
    if success:
        sys.exit(0)
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
